<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Datalayers\Search\QuerySearch;
use Doctrine\DBAL\Connection;

interface AddsJoinFilterInterface
{
    public function createJoinQuery(QuerySearch $querySearch, Connection $connection): string;
}
