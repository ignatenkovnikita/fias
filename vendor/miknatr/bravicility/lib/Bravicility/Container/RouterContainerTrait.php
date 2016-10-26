<?php

namespace Bravicility\Container;

use Bravicility\Router\Router;

trait RouterContainerTrait
{
    /** @var Router */
    private $routerInRouterTrait; // fucking php can actually have private names conflict with other fucking private names

    private $controllerDirsInRouterTrait;
    private $cacheDirInRouterTrait;
    private $skipCacheInvalidationInRouterTrait;

    abstract protected function ensureParameters(array $config, array $parameterNames);

    protected function loadRouterConfig($config, $packageRootDir)
    {
        $this->ensureParameters($config, array('router.skip_cache_invalidation'));
        $this->controllerDirsInRouterTrait        = array($packageRootDir . '/src/Controller');
        $this->cacheDirInRouterTrait              = $packageRootDir . '/cache/router';
        $this->skipCacheInvalidationInRouterTrait = $config['router.skip_cache_invalidation'] == 'on';
    }

    public function getRouter()
    {
        if (!$this->routerInRouterTrait) {
            $this->routerInRouterTrait = new Router(
                $this->controllerDirsInRouterTrait,
                $this->cacheDirInRouterTrait,
                !$this->skipCacheInvalidationInRouterTrait
            );
        }

        return $this->routerInRouterTrait;
    }
}
