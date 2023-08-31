<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Datalayers\Search\QuerySearch;
use Doctrine\DBAL\Connection;

interface EntityQueryFilterInterface
{
    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string;
}
