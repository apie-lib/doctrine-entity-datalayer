<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Attributes\ClassStoreOptions;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\DBAL\Connection;

class DefaultOptionFilter implements OrderByFilterInterface
{
    public function __construct(private readonly ClassStoreOptions $options)
    {
    }
    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string
    {
        return '1';
    }
    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        return sprintf(
            'entity.%s %s, entity.id %s',
            $this->options->defaultColumnName->value,
            $this->options->defaultSortingOrder->value,
            $this->options->defaultSortingOrder->value
        );
    }
}
