<?php
namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Lists\LazyLoadedList;
use Apie\Core\Datalayers\ValueObjects\LazyLoadedListIdentifier;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityDatalayer\Lists\CountDoctrineItems;
use Apie\DoctrineEntityDatalayer\Lists\GetDoctrineItem;
use Apie\DoctrineEntityDatalayer\Lists\TakeDoctrineItem;
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
     * @return LazyLoadedList<T>
     */
    public function createFor(ReflectionClass $entityClass, ReflectionClass $doctrineEntityClass, BoundedContextId $boundedContextId): LazyLoadedList
    {
        $filters = $this->entityQueryFilterFactory->createFilterList($doctrineEntityClass, $boundedContextId);
        $entityQueryFactory = new EntityQueryFactory(
            $this->ormBuilder->createEntityManager(),
            $doctrineEntityClass,
            $boundedContextId,
            ...$filters
        );
        return new LazyLoadedList(
            LazyLoadedListIdentifier::createFrom($boundedContextId, $entityClass),
            new GetDoctrineItem($this->ormBuilder, $entityQueryFactory),
            new TakeDoctrineItem($this->ormBuilder, $entityQueryFactory),
            new CountDoctrineItems($this->ormBuilder, $entityQueryFactory),
        );
    }
}
