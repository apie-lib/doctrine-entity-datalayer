<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\Context\ApieContext;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Indexing\Indexer;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use ReflectionClass;

final class EntityReindexer
{
    public function __construct(private readonly OrmBuilder $ormBuilder, private readonly Indexer $indexer)
    {
    }

    /**
     * @param ReflectionClass<HasIndexInterface> $doctrineEntity
     * @return class-string<object>
     */
    private function getIndexClass(ReflectionClass $doctrineEntity): string
    {
        return $doctrineEntity->getMethod('getIndexTable')->invoke(null)->name;
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
        EntityInterface $entity,
        bool $skipIdf = false
    ): void {
        $entityManager = $this->ormBuilder->createEntityManager();
        $newIndexes = $this->indexer->getIndexesForObject(
            $entity,
            new ApieContext()
        );
        $doctrineEntity->replaceIndexes($newIndexes);
        $termsToUpdate = array_keys($newIndexes);
        $entityManager->persist($doctrineEntity);
        $entityManager->flush();
        if (!$skipIdf) {
            $this->recalculateIdf($doctrineEntity, $termsToUpdate);
        }
    }

    /**
     * @param ReflectionClass<HasIndexInterface> $doctrineEntity
     */
    public function recalculateIdfForAll(ReflectionClass $doctrineEntity): void
    {
        $query = $this->createUpdateQuery($doctrineEntity);
        $entityManager = $this->ormBuilder->createEntityManager();
        $entityManager->getConnection()->executeQuery($query);
    }

    /**
     * @param ReflectionClass<HasIndexInterface> $doctrineEntity
     */
    private function createUpdateQuery(ReflectionClass $doctrineEntity): string
    {
        $entityManager = $this->ormBuilder->createEntityManager();
        $tableName = (new ReflectionClass($this->getIndexClass($doctrineEntity)))->getShortName();
        $columnName = 'ref_' . $doctrineEntity->getShortName() . '_id';
        $totalDocumentQuery = sprintf(
            '(SELECT total_documents FROM (SELECT COUNT(DISTINCT %s) AS total_documents FROM %s WHERE %s IS NOT NULL) AS sub1)',
            $columnName,
            $tableName,
            $columnName
        );
        $documentWithTermQuery = sprintf(
            'SELECT documents_with_term FROM (SELECT text, COUNT(DISTINCT %s) AS documents_with_term FROM %s WHERE %s IS NOT NULL GROUP BY text) AS sub WHERE sub.text',
            $columnName,
            $tableName,
            $columnName
        );
        $connection = $entityManager->getConnection();
        $query = sprintf(
            'UPDATE %s AS t
            SET idf = COALESCE(%s((%s)/(%s = t.text LIMIT 1)), 1)
            WHERE %s IS NOT NULL AND EXISTS (SELECT 1 FROM (SELECT text, COUNT(DISTINCT %s) AS documents_with_term FROM %s GROUP BY text) AS sub WHERE sub.text = t.text LIMIT 1);',
            $tableName,
            // @phpstan-ignore class.notFound
            $connection->getDatabasePlatform() instanceof SqlitePlatform ? '' : 'log',
            $totalDocumentQuery,
            $documentWithTermQuery,
            $columnName,
            $columnName,
            $tableName
        );

        return $query;
    }

    /**
     * @param array<int, string> $termsToUpdate
     */
    private function recalculateIdf(HasIndexInterface $doctrineEntity, array $termsToUpdate): void
    {
        if (empty($termsToUpdate)) {
            return;
        }
        $query = $this->createUpdateQuery(new ReflectionClass($doctrineEntity));
        $query = preg_replace('#LIMIT 1\);$#', 'AND t.text IN (:terms) LIMIT 1);', $query);
        $entityManager = $this->ormBuilder->createEntityManager();
        $entityManager->getConnection()->executeQuery(
            $query,
            ['terms' => array_values($termsToUpdate)],
            ['terms' => ArrayParameterType::STRING]
        );
    }
}
