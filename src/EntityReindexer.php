<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\Context\ApieContext;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Indexing\Indexer;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityConverter\PropertyGenerators\ManyToEntityReferencePropertyGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Mapping\OneToMany;
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
        $className = $this->getIndexClass($doctrineEntity);
        $res = new $className;
        $res->text = $text;
        $res->priority = $priority;
        $res->entity = $doctrineEntity;
        return $res;
    }

    /**
     * @return class-string<GeneratedDoctrineEntityInterface>
     */
    private function getIndexClass(GeneratedDoctrineEntityInterface $doctrineEntity): string
    {
        $refl = new ReflectionClass($doctrineEntity);

        $property = new ReflectionProperty($doctrineEntity, '_indexTable');
        $attributes = $property->getAttributes(OneToMany::class);
        foreach ($attributes as $attribute) {
            $className = $refl->getNamespaceName() . '\\' . $attribute->newInstance()->targetEntity;
            return $className;
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
        $termsToUpdate = array_keys($newIndexes);
        $offset = 0;
        $tf = 1.0 / array_sum($newIndexes);
        foreach ($newIndexes as $text => $priority) {
            if (isset($currentIndex[$offset])) {
                $termsToUpdate[] = $currentIndex[$offset]->text;
                $currentIndex[$offset]->text = $text;
                $currentIndex[$offset]->priority = $priority;
            } else {
                $currentIndex[$offset] = $this->createIndexClass($doctrineEntity, $text, $priority);
                $entityManager->persist($currentIndex[$offset]);
            }
            $currentIndex[$offset]->tf = $tf * $priority;
            $offset++;
        }
        $count = count($currentIndex);
        for (;$offset < $count; $offset++) {
            $termsToUpdate[] = $currentIndex[$offset]->text;
            $entityManager->remove($currentIndex[$offset]);
        }
        $doctrineEntity->_indexTable = $currentIndex;
        $entityManager->flush();
        $this->recalculateIdf($doctrineEntity, $termsToUpdate);
    }

    /**
     * @param array<int, string> $termsToUpdate
     */
    private function recalculateIdf(GeneratedDoctrineEntityInterface $doctrineEntity, array $termsToUpdate): void
    {
        if (empty($termsToUpdate)) {
            return;
        }
        $entityManager = $this->ormBuilder->createEntityManager();
        $tableName = (new ReflectionClass($this->getIndexClass($doctrineEntity)))->getShortName();
        $totalDocumentQuery = sprintf(
            'SELECT total_documents FROM (SELECT COUNT(DISTINCT entity_id) AS total_documents FROM %s)',
            $tableName
        );
        $documentWithTermQuery = sprintf(
            'SELECT documents_with_term FROM (SELECT text, COUNT(DISTINCT entity_id) AS documents_with_term FROM %s GROUP BY text) AS sub WHERE sub.text',
            $tableName
        );
        try {
            $query = sprintf(
                'UPDATE %s AS t
            SET idf = LOG((%s)/(%s = t.text))
            WHERE t.text IN (?) AND EXISTS (SELECT 1 FROM (SELECT text, COUNT(DISTINCT entity_id) AS documents_with_term FROM %s GROUP BY text) AS sub WHERE sub.text = t.text);',
                $tableName,
                $totalDocumentQuery,
                $documentWithTermQuery,
                $tableName
            );
            $entityManager->getConnection()
                ->executeQuery(
                    $query,
                    [$termsToUpdate],
                    [ArrayParameterType::STRING]
                );
        } catch (DriverException) {
            // TODO fallback
            $entityManager = $this->ormBuilder->createEntityManager();
            $connection = $entityManager->getConnection();
            $totalDocumentCount = $connection->prepare($totalDocumentQuery)->executeQuery()->fetchOne();
            $documentTermCount = $connection->
                executeQuery(
                    str_replace('SELECT ', 'SELECT text, ', $documentWithTermQuery . ' IN (?)'),
                    [$termsToUpdate],
                    [ArrayParameterType::STRING],
                )->fetchAllAssociativeIndexed();
            foreach ($documentTermCount as $searchTerm => $rowResult) {
                $idf = log10((1 + $totalDocumentCount) / (1 + $rowResult["documents_with_term"]));
                $query = sprintf(
                    'UPDATE %s SET idf = ? WHERE text = ?',
                    $tableName
                );
                $connection->executeQuery(
                    $query,
                    [$idf, $searchTerm],
                    [ParameterType::STRING, ParameterType::STRING]
                );
            }
        }
    }
}
