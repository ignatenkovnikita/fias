<?php

namespace Bravicility\Container;

use Grace\DBAL\ConnectionAbstract\ConnectionInterface;
use Grace\DBAL\ConnectionFactory;

trait DbContainerTrait
{
    /** @var ConnectionInterface */
    private $dbInDbTrait; // fucking php can actually have private names conflict with other fucking private names

    private $uriInDbTrait;

    abstract protected function ensureParameters(array $config, array $parameterNames);

    protected function loadDbConfig($config)
    {
        $this->ensureParameters($config, array('db.uri'));
        $this->uriInDbTrait = $config['db.uri'];
    }

    /** @return ConnectionInterface */
    public function getDb()
    {
        if (!$this->dbInDbTrait) {
            $uri = parse_url($this->uriInDbTrait);

            $this->dbInDbTrait = ConnectionFactory::getConnection(array(
                'adapter'  => ($uri['scheme'] == 'mysql') ? 'mysqli' : $uri['scheme'],
                'host'     => $uri['host'],
                'port'     => $uri['port'],
                'user'     => $uri['user'],
                'password' => $uri['pass'],
                'database' => trim($uri['path'], '/'),
            ));
        }
        return $this->dbInDbTrait;
    }
}
