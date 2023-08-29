<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Apie\Core\Datalayers\BoundedContextAwareApieDatalayer;
use Apie\Core\Datalayers\Lists\LazyLoadedList;
use Apie\Core\Datalayers\ValueObjects\LazyLoadedListIdentifier;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Identifiers\IdentifierInterface;
use Apie\Core\Indexing\Indexer;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityDatalayer\Exceptions\InsertConflict;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OneToMany;
use LogicException;
use ReflectionClass;
use ReflectionProperty;

class DoctrineEntityDatalayer implements BoundedContextAwareApieDatalayer
{
    private ?EntityManagerInterface $entityManager = null;

    public function __construct(private readonly OrmBuilder $ormBuilder, private readonly Indexer $indexer)
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
        $this->updateIndex($entityManager, $doctrineEntity, $entity);
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
        $this->updateIndex($entityManager, $doctrineEntity, $entity);
        return $entity;

    }

    private function createIndexClass(GeneratedDoctrineEntityInterface $doctrineEntity, string $text, float $priority): GeneratedDoctrineEntityInterface
    {
        $refl = new ReflectionClass($doctrineEntity);

        $property = new ReflectionProperty($doctrineEntity, '_indexTable');
        $attributes = $property->getAttributes(OneToMany::class);
        foreach ($attributes as $attribute) {
            $className = $refl->getNamespaceName() . '\\' . $attribute->newInstance()->targetEntity;
            $res = new $className;
            $res->text = $text;
            $res->priority = $priority;
            $res->entity = $doctrineEntity;
            return $res;
        }
        throw new LogicException('The _indexTable property should have a OneToMany attribute');
    }

    private function updateIndex(
        EntityManagerInterface $entityManager,
        GeneratedDoctrineEntityInterface $doctrineEntity,
        EntityInterface $entity
    ): void {
        $currentIndex = $doctrineEntity->_indexTable ?? new ArrayCollection([]);
        $newIndexes = $this->indexer->getIndexesForEntity(
            $entity,
            new ApieContext()
        );
        $offset = 0;
        foreach ($newIndexes as $text => $priority) {
            if (isset($currentIndex[$offset])) {
                $currentIndex[$offset]->text = $text;
                $currentIndex[$offset]->priority = $priority;
            } else {
                $currentIndex[$offset] = $this->createIndexClass($doctrineEntity, $text, $priority);
                $entityManager->persist($currentIndex[$offset]);
            }
            $offset++;
        }
        $count = count($currentIndex);
        for (;$offset < $count; $offset++) {
            $entityManager->remove($currentIndex[$offset]);
        }
        $doctrineEntity->_indexTable = $currentIndex;
        $entityManager->flush();
    }
}
