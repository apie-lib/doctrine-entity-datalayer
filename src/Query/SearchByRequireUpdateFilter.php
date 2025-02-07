<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\DBAL\Connection;

final class SearchByRequireUpdateFilter implements FieldSearchFilterInterface, OrderByFilterInterface
{
    public function getFilterName(): string
    {
        return 'dateToRecalculate';
    }

    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string
    {
        return 'entity.requires_update IS NOT NULL AND entity.requires_update <= CURRENT_TIMESTAMP()';
    }

    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        return 'entity.requires_update ' . $sortingOrder->value;
    }
}
