<?php

namespace Apie\DoctrineEntityDatalayer\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

class RunMigrationsOnConnect implements Middleware
{
    public function __construct(private readonly \Closure $action)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->action) implements Driver {
            private ?Connection $connection = null;

            public function __construct(private readonly Driver $driver, private readonly \Closure $action)
            {
            }

            public function connect(array $params): Connection
            {
                if ($this->connection === null) {
                    $this->connection = $this->driver->connect($params);
                    call_user_func($this->action);
                }

                return $this->connection;
            }
            
            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                return $this->driver->getDatabasePlatform($versionProvider);
            }

            public function getExceptionConverter(): ExceptionConverter
            {
                return $this->driver->getExceptionConverter();
            }
        };
    }
}
