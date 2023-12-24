<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\Context\ApieContext;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Indexing\Indexer;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use ReflectionClass;

final class EntityReindexer
{
    public function __construct(private readonly OrmBuilder $ormBuilder, private readonly Indexer $indexer)
    {
    }

    /**
     * @return class-string<HasIndexInterface>
     */
    private function getIndexClass(HasIndexInterface $doctrineEntity): string
    {
        $refl = new ReflectionClass($doctrineEntity);
        return $refl->getMethod('getIndexTable')->invoke($doctrineEntity)->name;
    }

    /**
     * Should be called after storing a doctrine entity from a domain entity. It recalculates the search terms
     * for the entity. For searching we use TF IDF and recalculate the TF of the entity. The IDF needs to be
     * recalculated in a separate function with an update query.
     *
     * @see https://en.wikipedia.org/wiki/Tf%E2%80%93idf
     */
    public function updateIndex(
        HasIndexInterface $doctrineEntity,
        EntityInterface $entity
    ): void {
        $entityManager = $this->ormBuilder->createEntityManager();
        $newIndexes = $this->indexer->getIndexesForObject(
            $entity,
            new ApieContext()
        );
        $doctrineEntity->replaceIndexes($newIndexes);
        $termsToUpdate = array_keys($newIndexes);
        $entityManager->flush();
        $this->recalculateIdf($doctrineEntity, $termsToUpdate);
    }

    /**
     * @param array<int, string> $termsToUpdate
     */
    private function recalculateIdf(HasIndexInterface $doctrineEntity, array $termsToUpdate): void
    {
        if (empty($termsToUpdate)) {
            return;
        }
        $entityManager = $this->ormBuilder->createEntityManager();
        $tableName = (new ReflectionClass($this->getIndexClass($doctrineEntity)))->getShortName();
        $columnName = 'ref_' . (new ReflectionClass($doctrineEntity))->getShortName() . '_id';
        $totalDocumentQuery = sprintf(
            'SELECT total_documents FROM (SELECT COUNT(DISTINCT %s) AS total_documents FROM %s)',
            $columnName,
            $tableName
        );
        $documentWithTermQuery = sprintf(
            'SELECT documents_with_term FROM (SELECT text, COUNT(DISTINCT %s) AS documents_with_term FROM %s GROUP BY text) AS sub WHERE sub.text',
            $columnName,
            $tableName
        );
        $connection = $entityManager->getConnection();
        $query = sprintf(
            'UPDATE %s AS t
            SET idf = %s((%s)/(%s = t.text))
            WHERE EXISTS (SELECT 1 FROM (SELECT text, COUNT(DISTINCT %s) AS documents_with_term FROM %s GROUP BY text) AS sub WHERE sub.text = t.text);',
            $tableName,
            $connection->getDatabasePlatform() instanceof SqlitePlatform ? '' : 'log',
            $totalDocumentQuery,
            $documentWithTermQuery,
            $columnName,
            $tableName
        );
        $connection->executeQuery($query);
    }
}
