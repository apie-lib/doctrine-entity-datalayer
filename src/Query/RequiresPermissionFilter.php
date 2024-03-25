<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Permissions\PermissionInterface;
use Apie\Core\Permissions\RequiresPermissionsInterface;
use Apie\Core\Attributes\LoggedIn;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\DBAL\Connection;
use ReflectionClass;

final class RequiresPermissionFilter implements TextSearchFilterInterface
{
    /**
     * @param ReflectionClass<EntityInterface&RequiresPermissionsInterface> $entityClass
     */
    public function __construct(
        private readonly ReflectionClass $entityClass,
        private readonly BoundedContextId $boundedContextId
    ) {
    }

    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string
    {
        $context = $querySearch->getApieContext();
        if ((new LoggedIn(PermissionInterface::class))->applies($context)) {
            return '1';
        }
        return '1 = 0';
    }

    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        return 'entity.id ' . $sortingOrder;
    }
}