<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\DBAL\Connection;

final class SearchByInternalColumnFilter implements FieldSearchFilterInterface, OrderByFilterInterface
{
    public function __construct(
        private readonly string $filterName,
        private readonly string $columnName,
    ) {
    }
    public function getFilterName(): string
    {
        return $this->filterName;
    }

    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string
    {
        return 'entity.`' . $this->columnName . '` IS NOT NULL AND entity.`' . $this->columnName . '` <= CURRENT_TIMESTAMP()';
    }

    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        return 'entity.`' . $this->columnName . '` ' . $sortingOrder->value;
    }
}
