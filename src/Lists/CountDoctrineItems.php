<?php
namespace Apie\DoctrineEntityDatalayer\Lists;

use Apie\Core\Datalayers\Interfaces\CountItems;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Factories\EntityQueryFactory;
use Apie\DoctrineEntityDatalayer\OrmBuilder;

final class CountDoctrineItems implements CountItems
{
    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly EntityQueryFactory $entityQueryFactory
    ) {
    }
    public function __invoke(QuerySearch $search): int
    {
        $entityQuery = $this->entityQueryFactory->createQueryFor($search);
        $entityManager = $this->ormBuilder->createEntityManager();

        $query = $entityManager->createQuery('SELECT COUNT(*) FROM (' . $entityQuery->getWithoutPagination() . ')');
        $result = $query->execute();
        return reset($result) ? : 0;
    }
}
