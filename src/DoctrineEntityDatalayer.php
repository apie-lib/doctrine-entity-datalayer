<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Datalayers\BoundedContextAwareApieDatalayer;
use Apie\Core\Datalayers\Lists\EntityListInterface;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Identifiers\IdentifierInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityDatalayer\Exceptions\InsertConflict;
use Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;

class DoctrineEntityDatalayer implements BoundedContextAwareApieDatalayer
{
    private ?EntityManagerInterface $entityManager = null;

    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly EntityReindexer $entityReindexer,
        private readonly DoctrineListFactory $doctrineListFactory
    ) {
    }

    private function getEntityManager(): EntityManagerInterface
    {
        if (!isset($this->entityManager) || !$this->entityManager->isOpen()) {
            $this->entityManager = $this->ormBuilder->createEntityManager();
        }

        return $this->entityManager;
    }

    public function all(ReflectionClass $class, ?BoundedContext $boundedContext = null): EntityListInterface
    {
        return $this->doctrineListFactory->createFor(
            $class,
            $this->ormBuilder->toDoctrineClass($class, $boundedContext),
            $boundedContext->getId()
        );
    }

    public function find(IdentifierInterface $identifier, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext)->name;
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
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext)->name;
        $doctrineEntity = $doctrineEntityClass::createFrom($entity);
        $entityManager->persist($doctrineEntity);
        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolation) {
            throw new InsertConflict($uniqueConstraintViolation);
        }

        $doctrineEntity->inject($entity);
        $this->entityReindexer->updateIndex($doctrineEntity, $entity);
        return $entity;
    }
    
    public function persistExisting(EntityInterface $entity, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext)->name;
        /** @var GeneratedDoctrineEntityInterface $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        $doctrineEntity->updateFrom($entity);
        $entityManager->persist($doctrineEntity);
        $entityManager->flush();

        $doctrineEntity->inject($entity);
        $this->entityReindexer->updateIndex($doctrineEntity, $entity);
        return $entity;

    }
}
