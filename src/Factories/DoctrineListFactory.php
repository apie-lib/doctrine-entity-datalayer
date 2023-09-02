<?php
namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Lists\EntityListInterface;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityDatalayer\Lists\DoctrineEntityList;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use ReflectionClass;

final class DoctrineListFactory
{
    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly EntityQueryFilterFactory $entityQueryFilterFactory
    ) {
    }

    /**
     * @template T of EntityInterface
     * @param ReflectionClass<T> $entityClass
     * @param ReflectionClass<GeneratedDoctrineEntityInterface> $doctrineEntityClass
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
            $entityQueryFactory,
            $doctrineEntityClass,
            $entityClass,
            $boundedContextId
        );
    }
}
