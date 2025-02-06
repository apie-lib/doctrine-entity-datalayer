<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\Attributes\LoggedIn;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\IdentifierUtils;
use Apie\Core\Permissions\PermissionInterface;
use Apie\Core\Permissions\RequiresPermissionsInterface;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Doctrine\DBAL\Connection;
use ReflectionClass;

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
