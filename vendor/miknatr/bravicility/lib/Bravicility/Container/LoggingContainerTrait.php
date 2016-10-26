<?php

namespace Bravicility\Container;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

trait LoggingContainerTrait
{
    /** @var Logger */
    private $errorLoggerInLoggingTrait; // fucking php can actually have private names conflict with other fucking private names

    private $errorLogInLoggingTrait;

    abstract protected function ensureParameters(array $config, array $parameterNames);

    protected function loadLoggingConfig($config, $packageRootDir)
    {
        $this->ensureParameters($config, array('logging.error_log'));

        $this->errorLogInLoggingTrait = $packageRootDir . '/' . $config['logging.error_log'];
    }

    public function getErrorLogger()
    {
        if (!$this->errorLoggerInLoggingTrait) {
            $this->errorLoggerInLoggingTrait = new Logger('errors');
            $this->errorLoggerInLoggingTrait->pushHandler(
                new StreamHandler($this->errorLogInLoggingTrait, Logger::DEBUG)
            );
        }

        return $this->errorLoggerInLoggingTrait;
    }
}
