<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\Context\ApieContext;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Indexing\Indexer;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityConverter\PropertyGenerators\ManyToEntityReferencePropertyGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;
use ReflectionClass;
use ReflectionProperty;

final class EntityReindexer
{
    public function __construct(private readonly OrmBuilder $ormBuilder, private readonly Indexer $indexer)
    {
    }

    /**
     * Creates an index class as used by the Doctrine entity. It makes assumptions about the generated Doctrine
     * entity.
     *
     * @see ManyToEntityReferencePropertyGenerator
     */
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

    /**
     * Should be called after storing a doctrine entity from a domain entity. It recalculates the search terms
     * for the entity. For searching we use TF IDF and recalculate the TF of the entity. The IDF needs to be
     * recalculated in a separate function with an update query.
     *
     * @see https://en.wikipedia.org/wiki/Tf%E2%80%93idf
     */
    public function updateIndex(
        GeneratedDoctrineEntityInterface $doctrineEntity,
        EntityInterface $entity
    ): void {
        $entityManager = $this->ormBuilder->createEntityManager();
        $currentIndex = $doctrineEntity->_indexTable ?? new ArrayCollection([]);
        $newIndexes = $this->indexer->getIndexesForObject(
            $entity,
            new ApieContext()
        );
        $offset = 0;
        $tf = 1.0 / count($newIndexes);
        foreach ($newIndexes as $text => $priority) {
            if (isset($currentIndex[$offset])) {
                $currentIndex[$offset]->text = $text;
                $currentIndex[$offset]->priority = $priority;
            } else {
                $currentIndex[$offset] = $this->createIndexClass($doctrineEntity, $text, $priority);
                $entityManager->persist($currentIndex[$offset]);
            }
            $currentIndex[$offset]->tf = $tf;
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
