<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\IdentifierUtils;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Stringable;

final class EntityQuery implements Stringable
{
    /** @var array<int, EntityQueryFilterInterface> */
    private array $filters = [];
    /** @var array<int, EntityQueryFilterInterface&AddsJoinFilterInterface> */
    private array $joinFilters = [];
    /** @var array<int, OrderByFilterInterface> */
    private array $orderByFilters = [];

    /**
     * @param ReflectionClass<EntityInterface> $entityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReflectionClass $entityClass,
        private readonly BoundedContextId $boundedContextId,
        private readonly QuerySearch $querySearch,
        EntityQueryFilterInterface... $filters
    ) {
        $searches = $querySearch->getSearches();
        $orderBy = $this->querySearch->getOrderBy();
        foreach ($filters as $filter) {
            $name = is_callable([$filter, 'getFilterName']) ? $filter->getFilterName() : '';
            if ($filter instanceof TextSearchFilterInterface && $querySearch->getTextSearch()) {
            } elseif ($filter instanceof FieldSearchFilterInterface && !empty($searches[$name])) {
            } elseif ($filter instanceof OrderByFilterInterface && !empty($orderBy[$name])) {
            } elseif ($filter instanceof RequiresPermissionFilter) {
            } else {
                continue;
            }
            $this->filters[] = $filter;
            if ($filter instanceof AddsJoinFilterInterface) {
                $this->joinFilters[] = $filter;
            }
            if ($filter instanceof OrderByFilterInterface) {
                $this->orderByFilters[] = $filter;
            }
        }
    }

    public function getWithoutPagination(): string
    {
        return sprintf(
            "SELECT DISTINCT entity.*
            FROM apie_resource__%s_%s entity%s
%s
GROUP BY entity.id
ORDER BY %s",
            $this->boundedContextId,
            IdentifierUtils::classNameToUnderscore($this->entityClass),
            $this->generateJoins(),
            $this->generateWhere(),
            $this->generateOrderBy(),
        );
    }

    public function __toString(): string
    {
        return $this->getWithoutPagination() . PHP_EOL . $this->generateOffset();
    }
    private function generateJoins(): string
    {
        if (empty($this->joinFilters)) {
            return '';
        }
        $connection = $this->entityManager->getConnection();
        $joinSql = implode(
            PHP_EOL,
            array_filter(
                array_map(
                    function (EntityQueryFilterInterface&AddsJoinFilterInterface $filter) use ($connection) {
                        return $filter->createJoinQuery($this->querySearch, $connection);
                    },
                    $this->joinFilters
                )
            )
        );
        return PHP_EOL . $joinSql;
    }

    private function generateWhere(): string
    {
        if (empty($this->filters)) {
            return '';
        }
        $connection = $this->entityManager->getConnection();
        $whereSql = implode(
            ')' . PHP_EOL . 'AND (',
            array_map(
                function (EntityQueryFilterInterface $filter) use ($connection) {
                    return $filter->getWhereCondition($this->querySearch, $connection);
                },
                $this->filters
            )
        );
        return 'WHERE (' . $whereSql . ')';
    }

    private function generateOrderBy(): string
    {
        $orderBy = $this->querySearch->getOrderBy()->toArray();
        $query = implode(
            ', ',
            array_filter(
                array_map(
                    function (OrderByFilterInterface $filter) use ($orderBy) {
                        if (is_callable([$filter, 'getFilterName'])) {
                            $filterName = $filter->getFilterName();
                            if (isset($orderBy[$filterName])) {
                                return $filter->getOrderByCode(SortingOrder::from($orderBy[$filterName]));
                            }
                        }
                        return $filter->getOrderByCode(SortingOrder::DESCENDING);
                    },
                    $this->orderByFilters
                )
            )
        );
        
        return empty($query) ? 'entity.created_at DESC' : $query;
    }

    private function generateOffset(): string
    {
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        return $platform->modifyLimitQuery(
            '',
            $this->querySearch->getItemsPerPage(),
            ($this->querySearch->getPageIndex() * $this->querySearch->getItemsPerPage()),
        );
    }
}
