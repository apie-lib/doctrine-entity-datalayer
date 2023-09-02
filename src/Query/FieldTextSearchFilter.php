<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\LikeUtils;
use Doctrine\DBAL\Connection;

final class FieldTextSearchFilter implements FieldSearchFilterInterface
{
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
        return 'entity.'
            . $this->propertyName
            . ' = '
            . $connection->quote(LikeUtils::toLikeString($querySearch->getSearches()[$this->filterName]));
    }
}
