<?php

namespace Bravicility\Router;

class RouteProvider
{
    /** @var string[] */
    private $controllerDirs = array();

    public function __construct(array $controllerDirs)
    {
        $this->controllerDirs = $controllerDirs;
    }

    /** @return array */
    public function generateRoutes()
    {
        $controllerFiles = array();
        foreach ($this->controllerDirs as $dir) {
            $controllerFiles = array_merge($controllerFiles, getFilesRecursively($dir));
        }

        $classes = array();
        foreach ($controllerFiles as $file) {
            $content = file_get_contents($file);

            $namespace = '';
            if (preg_match('/^namespace\s+(\S+);$/m', $content, $namespaceMatch)) {
                $namespace = '\\' . $namespaceMatch[1];
            }

            if (!preg_match('/^class\s+(\S+)(?:(?:\s+extends|\s+implements|\s*\{)|$)/m', $content, $classMatch)) {
                continue;
            }
            $class = $classMatch[1];

            $classes[] = $namespace . '\\' . $class;
        }

        $routes = array();
        foreach ($classes as $class) {
            $rClass = new \ReflectionClass($class);

            $classPattern  = '';
            $classDefaults = array();
            $classPriority = 0;
            foreach (getPhpdocTags($rClass->getDocComment()) as $annotation) {
                if ($annotation['name'] != 'route') {
                    continue;
                }

                if (!empty($classPattern)) {
                    throw new \Exception("Class {$class} has more than one @route annotation");
                }

                $classPriority = $this->shiftPriorityFromArgs($annotation['args']);

                if (empty($annotation['args'])) {
                    throw new \Exception("Class {$class} has incorrect @route annotation (syntax: @route [priority=123] /blah)");
                }

                $classPattern  = array_shift($annotation['args']);
                $classDefaults = $annotation['args'];
            }

            $rMethods = $rClass->getMethods();
            foreach ($rMethods as $rMethod) {
                if ($rMethod->isAbstract() || !$rMethod->isPublic() || $rMethod->isStatic()) {
                    continue;
                }

                foreach (getPhpdocTags($rMethod->getDocComment()) as $annotation) {
                    if ($annotation['name'] != 'route') {
                        continue;
                    }

                    // TODO файлы и строки/методы в тексты исключений

                    $priority = $this->shiftPriorityFromArgs($annotation['args'], $classPriority);

                    if (!empty($annotation['args']) && preg_match('/^priority=(-?\d+)$/', $annotation['args'][0], $match)) {
                        $priority = (int) $match[1];
                        array_shift($annotation['args']);
                    }

                    if (count($annotation['args']) < 2) {
                        throw new \Exception('Incorrect annotation: ' . $annotation['string']. ' (syntax: @route [priority=123] GET /blah)');
                    }

                    $method = array_shift($annotation['args']);
                    if (!preg_match('/^[A-Z]+$/', $method)) {
                        throw new \Exception('Incorrect HTTP method in annotation: ' . $annotation['string']);
                    }

                    // class route "/blah" and method route "/" should give "/blah", not "/blah/"
                    $pattern = rtrim($classPattern . array_shift($annotation['args']), '/');

                    $defaultDefs = array_merge($classDefaults, $annotation['args']);

                    $defaults = array();
                    foreach ($defaultDefs as $def) {
                        $parts = explode('=', $def, 2);
                        if (count($parts) != 2) {
                            throw new \Exception("Incorrect route default: {$def} in " . $annotation['string']);
                        }
                        $defaults[$parts[0]] = $parts[1];
                    }

                    $routes[] = array(
                        'callback' => array($class, $rMethod->getName()),
                        'method'   => $method,
                        'pattern'  => $pattern,
                        'regexp'   => static::makeRouteRegexp($pattern),
                        'defaults' => $defaults,
                        'priority' => $priority,
                    );
                }
            }
        }

        // STOPPER финансер не работает, нужно или сделать поддержку форматов или как-то мозг не ебать, или в разделители добавить кроме / еще всякие _,-.
        //$this->ensureNoOverlappingRoutes($routes);

        $routes = $this->sortRoutesByPriority($routes);

        // we don't want to cache unnecessary data
        foreach ($routes as $i => $route) {
            unset($routes[$i]['pattern'], $routes[$i]['priority']);
        }

        return $routes;
    }

    private function shiftPriorityFromArgs(&$args, $defaultPriority = 0)
    {
        if (!empty($args) && preg_match('/^priority=(-?\d+)$/', $args[0], $match)) {
            array_shift($args);
            return (int) $match[1];
        }
        return $defaultPriority;
    }

    private function ensureNoOverlappingRoutes($routes)
    {
        $patterns      = array();
        $routesByGroup = array();
        foreach ($routes as $route) {
            $group = $route['priority'] . '_' . $route['method'];
            $patterns[$group][] = $route['pattern'];
            $routesByGroup[$group][] = $route;
        }
        foreach ($patterns as $group => $list) {
            $overlappingPatterns = static::findOverlappingPatterns($list);

            if (!empty($overlappingPatterns)) {
                $priority = (int) $group;

                $descs = array();
                foreach (array_unique($overlappingPatterns) as $pattern) {
                    foreach ($routesByGroup[$group] as $route) {
                        if ($route['pattern'] == $pattern) {
                            $descs[$pattern][] = join('::', $route['callback']);
                        }
                    }
                    $descs[$pattern] = $pattern . ' (' . join(', ', $descs[$pattern]) . ')';
                }
                throw new \LogicException("These routes (priority={$priority}) overlap: " . join(', ', $descs));
            }
        }
    }

    public static function findOverlappingPatterns(array $patterns)
    {
        $groups        = array();
        $finalSections = array();
        foreach ($patterns as $pattern => $desc) {
            // мы хотим сюда вкормить список шаблонов, сделанный из роутов
            // он может быть не уникален уже там, но проверять это в двух разных местах мы не хотим
            // на втором уровне рекурсии список шаблонов гарантированно уникальный
            // так что тут мы делаем хаке с лососем, чтобы без геморроя работать с массивом
            if (is_int($pattern)) {
                $pattern = $desc;
            }

            // мы пока что умеем проверять только роуты вида /fixed/{var}/other_fixed
            // роуты вида /prefix{var} мы не умеем проверять, но пока что и не хотим
            // также проблему создают двойные слеши
            if (preg_match('#[^/]\{|\}[^/]|//#', $pattern)) {
                throw new \LogicException('Cannot check route pattern for overlapping: ' . $pattern);
            }

            // $pattern is guaranteed to have a leading slash and no slash at end
            $parts = explode('/', $pattern, 2);
            $head = $parts[0];
            $tail = isset($parts[1]) ? $parts[1] : null;

            if (substr($head, 0, 1) == '{') {
                $head = '{}';
            }

            if ($tail === null) {
                // if final sections are not unique, that's an overlap (in both variable and fixed cases)
                if (isset($finalSections[$head])) {
                    return array($finalSections[$head], $desc);
                }
                $finalSections[$head] = $desc;
            } else {
                if (isset($groups[$head][$tail])) {
                    return array($groups[$head][$tail], $desc);
                }
                $groups[$head][$tail] = $desc;
            }
        }

        // if there is a variable final section and a fixed one next to it, that's an overlap
        // (we already know that fixed final sections are unique)
        if (isset($finalSections['{}']) && count($finalSections) > 1) {
            return array_values($finalSections);
        }

        // if there is a variable head, it means all tails we have must not overlap
        if (isset($groups['{}'])) {
            $allTails = array();
            foreach ($groups as $tails) {
                foreach ($tails as $tail => $desc) {
                    if (isset($allTails[$tail])) {
                        return array($allTails[$tail], $desc);
                    }
                    $allTails[$tail] = $desc;
                }
            }
            return static::findOverlappingPatterns($allTails);
        }

        // only fixed heads = for each head, tails must not overlap
        foreach ($groups as $tails) {
            $overlap = static::findOverlappingPatterns($tails);
            if (!empty($overlap)) {
                return $overlap;
            }
        }

        return array();
    }

    private function sortRoutesByPriority(array $routes)
    {
        usort($routes, function ($a, $b) {
            return $b['priority'] - $a['priority']; // more priority = earlier check
        });

        return $routes;
    }

    private static function makeRouteRegexp($pattern)
    {
        $regexp = '';

        $parts = preg_split('/(\{\w+\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2) {
                // нечетная = {переменная}
                $name = preg_quote(substr($part, 1, -1), '#');
                $regexp .= '(?<' . $name . '>[^/]+)';
            } else {
                // четная = кусок урла
                $regexp .= preg_quote($part, '#');
            }
        }

        return "#^{$regexp}/?$#";
    }
}
