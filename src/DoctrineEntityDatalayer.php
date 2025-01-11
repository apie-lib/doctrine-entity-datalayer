<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
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
use Doctrine\ORM\Exception\EntityIdentityCollisionException;
use ReflectionClass;
use ReflectionProperty;

class DoctrineEntityDatalayer implements ApieDatalayerWithFilters
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

    public function all(ReflectionClass $class, ?BoundedContextId $boundedContextId = null): EntityListInterface
    {
        return $this->doctrineListFactory->createFor(
            $class,
            $this->ormBuilder->toDoctrineClass($class, $boundedContextId),
            $boundedContextId ?? new BoundedContextId('unknown')
        );
    }

    public function find(IdentifierInterface $identifier, ?BoundedContextId $boundedContextId = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContextId)->name;
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        if (!($doctrineEntity instanceof StorageDtoInterface)) {
            throw new EntityNotFoundException($identifier);
        }
        assert($doctrineEntity instanceof RootObjectInterface);
        DoctrineUtils::loadAllProxies($doctrineEntity);
        return $this->domainToStorageConverter->createDomainObject($doctrineEntity);
    }
    
    public function persistNew(EntityInterface $entity, ?BoundedContextId $boundedContextId = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        /** @var class-string<StorageDtoInterface> $doctrineEntityClass */
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContextId)->name;
        $doctrineEntity = $this->domainToStorageConverter->createStorageObject(
            $entity,
            new ReflectionClass($doctrineEntityClass)
        );

        try {
            $entityManager->persist($doctrineEntity);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException|EntityIdentityCollisionException $uniqueConstraintViolation) {
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
    
    public function persistExisting(EntityInterface $entity, ?BoundedContextId $boundedContextId = null): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContextId)->name;
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

    public function removeExisting(EntityInterface $entity, ?BoundedContextId $boundedContextId = null): void
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContextId)->name;
        /** @var (StorageDtoInterface&RootObjectInterface)|null $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass, $identifier->toNative());
        if (!$doctrineEntity) {
            throw new EntityNotFoundException($identifier);
        }
        $entityManager->remove($doctrineEntity);
        $entityManager->flush();
    }

    public function upsert(EntityInterface $entity, ?BoundedContextId $boundedContextId): EntityInterface
    {
        $entityManager = $this->getEntityManager();
        $identifier = $entity->getId();
        $domainClass = $identifier->getReferenceFor();
        $doctrineEntityClass = $this->ormBuilder->toDoctrineClass($domainClass, $boundedContextId);
        /** @var (StorageDtoInterface&RootObjectInterface)|null $doctrineEntity */
        $doctrineEntity = $entityManager->find($doctrineEntityClass->name, $identifier->toNative());
        if ($doctrineEntity) {
            $this->domainToStorageConverter->injectExistingStorageObject(
                $entity,
                $doctrineEntity
            );
        } else {
            $doctrineEntity = $this->domainToStorageConverter->createStorageObject(
                $entity,
                $doctrineEntityClass
            );
        }
        $entityManager->persist($doctrineEntity);
        $entityManager->flush();

        if ($doctrineEntity instanceof HasIndexInterface) {
            $this->entityReindexer->updateIndex($doctrineEntity, $entity);
        }
        return $entity;
    }
}
