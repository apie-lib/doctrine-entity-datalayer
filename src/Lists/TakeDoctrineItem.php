<?php
namespace Apie\DoctrineEntityDatalayer\Lists;

use Apie\Core\Datalayers\Interfaces\TakeItem;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityDatalayer\Factories\EntityQueryFactory;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * @implements TakeItem<EntityInterface>
 */
final class TakeDoctrineItem implements TakeItem
{
    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly EntityQueryFactory $entityQueryFactory
    ) {
    }
    public function __invoke(int $index, int $count, QuerySearch $search): array
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
        return array_slice($result, $index, $count);
        
    }
}
