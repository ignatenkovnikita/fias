<?php

namespace Bravicility\Router;

class Router
{
    /**
     * @var array
     * array(
     *     array(
     *         'method'   => 'GET',
     *         'regexp'   => '#^/$#',
     *         'callback' => array('\\Controller\\IndexController', 'index'),
     *         'defaults' => array('var' => 'val'),
     *     ),
     * ),
     */
    private $routeRules;

    public function __construct(array $controllerDirs, $routesCacheDir, $autoRefreshCache = true)
    {
        $cacheTimeFile = $routesCacheDir . '/cache_time';

        $needRefresh = false;
        if (!file_exists($routesCacheDir)) {
            mkdir($routesCacheDir);
            $needRefresh = true;
        } elseif ($autoRefreshCache) {
            $cacheTime = file_exists($cacheTimeFile) ? file_get_contents($cacheTimeFile) : 0;
            if ($cacheTime < static::getLastControllerModTime($controllerDirs)) {
                $needRefresh = true;
            }
        }

        $rulesFile = $routesCacheDir . '/route_rules';

        if ($needRefresh) {
            $routeRules = (new RouteProvider($controllerDirs, $routesCacheDir))->generateRoutes();
            file_put_contents($cacheTimeFile, static::getLastControllerModTime($controllerDirs));
            file_put_contents($rulesFile, serialize($routeRules));
        }

        $this->routeRules = unserialize(file_get_contents($rulesFile));
    }

    protected static function getLastControllerModTime(array $controllerDirs)
    {
        $controllerFiles = array();
        foreach ($controllerDirs as $dir) {
            $controllerFiles = array_merge($controllerFiles, getFilesRecursively($dir));
        }

        $lastTime = 0;
        foreach ($controllerFiles as $file) {
            $fileModTime = filemtime($file);
            if ($fileModTime > $lastTime) {
                $lastTime = $fileModTime;
            }
        }

        return $lastTime;
    }

    /**
     * @param string $method
     * @param string $urlPath
     * @return Route
     */
    public function route($method, $urlPath)
    {
        foreach ($this->routeRules as $rule) {
            if ($method != $rule['method']) {
                continue;
            }

            if (!preg_match($rule['regexp'], $urlPath, $match)) {
                continue;
            }

            $vars = $rule['defaults'];
            foreach ($match as $k => $v) {
                if (!is_numeric($k)) {
                    $vars[$k] = $v;
                }
            }

            $route = new Route();
            $route->class = $rule['callback'][0];
            $route->method = $rule['callback'][1];
            $route->vars = $vars;

            return $route;
        }

        throw new RouteNotFoundException("Route not found for $method $urlPath");
    }
}
