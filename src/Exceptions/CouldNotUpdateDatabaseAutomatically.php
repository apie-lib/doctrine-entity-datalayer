<?php
namespace Apie\DoctrineEntityDatalayer\Exceptions;

use Apie\Core\Exceptions\ApieException;
use Doctrine\DBAL\Exception\DriverException;

class CouldNotUpdateDatabaseAutomatically extends ApieException
{
    public function __construct(DriverException $previous)
    {
        parent::__construct(
            sprintf(
                'Could not update database automatically: "%s"',
                $previous->getMessage()
            ),
            0,
            $previous
        );
    }
}
