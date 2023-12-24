<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use Apie\Core\Datalayers\BoundedContextAwareApieDatalayer;
use Apie\Core\Datalayers\Lists\EntityListInterface;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Exceptions\EntityNotFoundException;
use Apie\Core\Identifiers\IdentifierInterface;
use Apie\Core\Lists\StringList;
use Apie\DoctrineEntityDatalayer\Exceptions\InsertConflict;
use Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory;
use Apie\StorageMetadata\Attributes\GetSearchIndexAttribute;
use Apie\StorageMetadata\DomainToStorageConverter;
use Apie\StorageMetadata\Interfaces\StorageDtoInterface;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;
use Apie\StorageMetadataBuilder\Interfaces\RootObjectInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use ReflectionProperty;

class DoctrineEntityDatalayer implements ApieDatalayerWithFilters, BoundedContextAwareApieDatalayer
{
    private ?EntityManagerInterface $entityManager = null;

    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly DomainToStorageConverter $domainToStorageConverter,
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

    public function getFilterColumns(ReflectionClass $class, BoundedContextId $boundedContextId): StringList
    {
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($class);
        $list = [];
        foreach ($doctrineEntityClass->getProperties(ReflectionProperty::IS_PUBLIC) as $publicProperty) {
            if (str_starts_with($publicProperty->name, 'search_')) {
                foreach ($publicProperty->getAttributes(GetSearchIndexAttribute::class) as $publicPropertyAttribute) {
                    $list[] = substr($publicProperty->name, strlen('search_'));
                }
            }
        }
        return new StringList($list);
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
        /** @var (RootObjectInterface&StorageDtoInterface)|null $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        if (!$doctrineEntity) {
            throw new EntityNotFoundException($identifier);
        }
        assert($doctrineEntity instanceof RootObjectInterface);
        assert($doctrineEntity instanceof StorageDtoInterface);
        return $this->domainToStorageConverter->createDomainObject($doctrineEntity);
    }
    public function persistNew(EntityInterface $entity, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        /** @var class-string<StorageDtoInterface> $doctrineEntityClass */
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext)->name;
        $doctrineEntity = $this->domainToStorageConverter->createStorageObject(
            $entity,
            new ReflectionClass($doctrineEntityClass)
        );
        $entityManager->persist($doctrineEntity);
        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $uniqueConstraintViolation) {
            throw new InsertConflict($uniqueConstraintViolation);
        }
        // TODO: only do for entities with Auto-increment id's
        $this->domainToStorageConverter->injectExistingDomainObject(
            $entity,
            $doctrineEntity
        );
        if ($doctrineEntity instanceof HasIndexInterface) {
            $this->entityReindexer->updateIndex($doctrineEntity, $entity);
        }
        
        return $entity;
    }
    
    public function persistExisting(EntityInterface $entity, ?BoundedContext $boundedContext = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext)->name;
        /** @var (StorageDtoInterface&RootObjectInterface)|null $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        if (!$doctrineEntity) {
            throw new EntityNotFoundException($identifier);
        }
        $this->domainToStorageConverter->injectExistingStorageObject(
            $entity,
            $doctrineEntity
        );
        $entityManager->persist($doctrineEntity);
        $entityManager->flush();

        if ($doctrineEntity instanceof HasIndexInterface) {
            $this->entityReindexer->updateIndex($doctrineEntity, $entity);
        }
        return $entity;
    }

    public function removeExisting(EntityInterface $entity, ?BoundedContext $boundedContext = null): void
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContext)->name;
        /** @var (StorageDtoInterface&RootObjectInterface)|null $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        if (!$doctrineEntity) {
            throw new EntityNotFoundException($identifier);
        }
        $entityManager->remove($doctrineEntity);
        $entityManager->flush();
    }
}
