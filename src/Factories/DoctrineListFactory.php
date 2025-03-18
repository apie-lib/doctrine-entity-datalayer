<?php
namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Lists\EntityListInterface;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityDatalayer\Lists\DoctrineEntityList;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Apie\StorageMetadata\DomainToStorageConverter;
use ReflectionClass;

final class DoctrineListFactory
{
    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly EntityQueryFilterFactory $entityQueryFilterFactory,
        private readonly DomainToStorageConverter $domainToStorageConverter
    ) {
    }

    /**
     * @template T of EntityInterface
     * @param ReflectionClass<T> $entityClass
     * @param ReflectionClass<object> $doctrineEntityClass
     * @return EntityListInterface<T>
     */
    public function createFor(ReflectionClass $entityClass, ReflectionClass $doctrineEntityClass, BoundedContextId $boundedContextId): EntityListInterface
    {
        $filters = $this->entityQueryFilterFactory->createFilterList($doctrineEntityClass, $boundedContextId);
        $entityQueryFactory = new EntityQueryFactory(
            $this->ormBuilder->createEntityManager(),
            $doctrineEntityClass,
            $boundedContextId,
            ...$filters
        );
        return new DoctrineEntityList(
            $this->ormBuilder,
            $this->domainToStorageConverter,
            $entityQueryFactory,
            $entityClass,
            $boundedContextId
        );
    }
}
