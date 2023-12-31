<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Datalayers\BoundedContextAwareApieDatalayer;
use Apie\Core\Datalayers\Lists\LazyLoadedList;
use Apie\Core\Datalayers\ValueObjects\LazyLoadedListIdentifier;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Identifiers\IdentifierInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;

class DoctrineEntityDatalayer implements BoundedContextAwareApieDatalayer
{
    private ?EntityManagerInterface $entityManager = null;

    public function __construct(private readonly OrmBuilder $ormBuilder)
    {
    }

    private function getEntityManager(): EntityManagerInterface
    {
        if (!isset($this->entityManager)) {
            $this->entityManager = $this->ormBuilder->createEntityManager();
        }

        return $this->entityManager;
    }

    public function all(ReflectionClass $class, ?BoundedContext $boundedContext = null): LazyLoadedList
    {
        // TODO
        return LazyLoadedList::createFromArray(LazyLoadedListIdentifier::createFrom($boundedContext->getId(), $class), []);
    }
    public function find(IdentifierInterface $identifier, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext);
        /** @var GeneratedDoctrineEntityInterface $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        $domainObject = $domainClass->newInstanceWithoutConstructor();
        $doctrineEntity->inject($domainObject);
        return $domainObject;
    }
    public function persistNew(EntityInterface $entity, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext);
        $doctrineEntity = $doctrineEntityClass::createFrom($entity);
        $entityManager->persist($doctrineEntity);
        $entityManager->flush();

        $doctrineEntity->inject($entity);
        return $entity;
    }
    public function persistExisting(EntityInterface $entity, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext);
        /** @var GeneratedDoctrineEntityInterface $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        $doctrineEntity->updateFrom($entity);
        $entityManager->persist($doctrineEntity);
        $entityManager->flush();

        $doctrineEntity->inject($entity);
        return $entity;

    }
}
