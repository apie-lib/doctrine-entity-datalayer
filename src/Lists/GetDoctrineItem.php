<?php
namespace Apie\DoctrineEntityDatalayer\Lists;

use Apie\Core\Datalayers\Interfaces\GetItem;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityDatalayer\Factories\EntityQueryFactory;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * @implements GetItem<EntityInterface>
 */
final class GetDoctrineItem implements GetItem
{
    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly EntityQueryFactory $entityQueryFactory
    ) {
    }
    public function __invoke(int $index, QuerySearch $search): EntityInterface
    {
        $entityQuery = $this->entityQueryFactory->createQueryFor($search);
        $entityManager = $this->ormBuilder->createEntityManager();
        $resultSetMapping = new ResultSetMappingBuilder($entityManager);
        $resultSetMapping->addRootEntityFromClassMetadata(
            $this->entityQueryFactory->getDoctrineClass()->name,
            'entity'
        );
        $query = $entityManager->createNativeQuery((string) $entityQuery, $resultSetMapping);
        $result = $query->execute();
        return $result[$index];
        
    }
}
