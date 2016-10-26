<?php

namespace Bravicility\Container;

trait TwigContainerTrait
{
    /** @var \Twig_Environment */
    private $twigInTwigTrait; // fucking php can actually have private names conflict with other fucking private names

    private $templateDirInTwigTrait;
    private $skipCacheInvalidationInTwigTrait;
    private $cacheDirInTwigTrait;

    abstract protected function ensureParameters(array $config, array $parameterNames);

    protected function loadTwigConfig($config, $packageRootDir)
    {
        $this->ensureParameters($config, array('twig.skip_cache_invalidation'));

        $this->templateDirInTwigTrait           = $packageRootDir . '/src/views';
        $this->cacheDirInTwigTrait              = $packageRootDir . '/cache/twig';
        $this->skipCacheInvalidationInTwigTrait = $config['twig.skip_cache_invalidation'] == 'on';
    }

    public function getTwig()
    {
        if (!$this->twigInTwigTrait) {
            $loader = new \Twig_Loader_Filesystem($this->templateDirInTwigTrait);
            $this->twigInTwigTrait = new \Twig_Environment($loader, array(
                'debug'            => false,
                'strict_variables' => true,
                'auto_reload'      => !$this->skipCacheInvalidationInTwigTrait,
                'autoescape'       => true,
                'cache'            => $this->cacheDirInTwigTrait,
            ));
            $this->twigInTwigTrait->addFilter(new \Twig_SimpleFilter('dt2RA_ru', 'dt2RA_ru'));
            $this->twigInTwigTrait->addFilter(new \Twig_SimpleFilter('money', 'formatMoney'));
            $this->twigInTwigTrait->addFilter(new \Twig_SimpleFilter('url', 'urlencode'));
            $this->twigInTwigTrait->addFilter(new \Twig_SimpleFilter('formatPhone', 'formatPhone'));
        }

        return $this->twigInTwigTrait;
    }
}
