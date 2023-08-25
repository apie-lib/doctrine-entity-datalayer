<?php
namespace Apie\DoctrineEntityDatalayer\Exceptions;

use Apie\Core\Exceptions\ApieException;
use Apie\Core\Exceptions\HttpStatusCodeException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class InsertConflict extends ApieException implements HttpStatusCodeException
{
    public function getStatusCode(): int
    {
        return 409;
    }

    public function __construct(UniqueConstraintViolationException $previous)
    {
        parent::__construct('Insertion conflict, unique constraint already exists', 0, $previous);
    }
}