<?php

namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\DBAL\Connection;

class OrderBySearchFilter implements OrderByFilterInterface
{
    private ?string $search = null;
    public function __construct(
        private readonly string $filterName,
        private readonly string $propertyName
    ) {
    }

    public function getFilterName(): string
    {
        return $this->filterName;
    }

    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string
    {
        $this->search = $querySearch->getOrderBy()[$this->filterName] ?? null;
        return '1';
    }

    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        if ($this->search === null) {
            return '';
        }
        return 'entity.' . $this->propertyName . ' ' . $sortingOrder->value;
    }
}
