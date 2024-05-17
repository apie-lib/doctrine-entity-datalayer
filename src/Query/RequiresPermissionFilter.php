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

final class RequiresPermissionFilter implements TextSearchFilterInterface, AddsJoinFilterInterface
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
            $user = $context->getContext(ContextConstants::AUTHENTICATED_USER);
            assert($user instanceof PermissionInterface);
            $permissions = $user->getPermissionIdentifiers()->toStringList()->toArray();
            if (empty($permissions)) {
                return '1 = 0';
            }
            $query = array_map(
                function (string $permission) use ($connection) {
                    return $connection->quote($permission);
                },
                $permissions
            );
            return sprintf('acl.permission IN (%s)', implode(',', $query));
        }
        return '1 = 0';
    }

    public function createJoinQuery(QuerySearch $querySearch, Connection $connection): string
    {
        return sprintf(
            'JOIN apie_access_control_list acl ON (entity.id = acl.ref_apie_resource__%s_%s_id)',
            $this->boundedContextId,
            IdentifierUtils::classNameToUnderscore($this->entityClass),
        );
    }

    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        return 'entity.id ' . $sortingOrder->value;
    }
}
