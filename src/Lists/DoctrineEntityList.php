<?php
namespace Apie\DoctrineEntityDatalayer\Lists;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Context\ApieContext;
use Apie\Core\Datalayers\Lists\EntityListInterface;
use Apie\Core\Datalayers\Lists\PaginatedResult;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Datalayers\ValueObjects\LazyLoadedListIdentifier;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Lists\ItemList;
use Apie\DoctrineEntityDatalayer\Factories\EntityQueryFactory;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Apie\StorageMetadata\DomainToStorageConverter;
use Apie\StorageMetadata\Interfaces\StorageDtoInterface;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Iterator;
use ReflectionClass;

/**
 * @template T of EntityInterface
 * @implements EntityListInterface<T>
 */
final class DoctrineEntityList implements EntityListInterface
{
    /**
     * @param ReflectionClass<T> $entityClass
     */
    public function __construct(
        private readonly OrmBuilder $ormBuilder,
        private readonly DomainToStorageConverter $domainToStorageConverter,
        private readonly EntityQueryFactory $entityQueryFactory,
        private readonly ReflectionClass $entityClass,
        private readonly BoundedContextId $boundedContextId
    ) {
    }

    public function getIterator(): Iterator
    {
        $query = $this->createNativeQuery(new QuerySearch(0), noPagination: true);
        /** @var StorageDtoInterface $rowResult */
        foreach ($query->toIterable() as $rowResult) {
            yield $this->domainToStorageConverter->createDomainObject($rowResult);
        }
    }

    private function createNativeQuery(QuerySearch $querySearch, bool $noPagination): NativeQuery
    {
        $entityQuery = $this->entityQueryFactory->createQueryFor($querySearch);
        $entityManager = $this->ormBuilder->createEntityManager();
        $resultSetMapping = new ResultSetMappingBuilder($entityManager);
        $resultSetMapping->addRootEntityFromClassMetadata(
            $this->entityQueryFactory->getDoctrineClass()->name,
            'entity'
        );
        
        if ($noPagination) {
            return $entityManager->createNativeQuery($entityQuery->getWithoutPagination(), $resultSetMapping);
        }
        return $entityManager->createNativeQuery((string) $entityQuery, $resultSetMapping);
    }

    /**
     * @return PaginatedResult<T>
     */
    public function toPaginatedResult(QuerySearch $search, ApieContext $apieContext = new ApieContext()): PaginatedResult
    {
        $query = $this->createNativeQuery($search, noPagination: false);
        $list = [];
        foreach ($query->toIterable() as $rowResult) {
            $list[] = $this->domainToStorageConverter->createDomainObject($rowResult);
        }
        return new PaginatedResult(
            LazyLoadedListIdentifier::createFrom($this->boundedContextId, $this->entityClass),
            $this->getTotalCount(),
            new ItemList($list),
            $search->getPageIndex(),
            $search->getItemsPerPage(),
            $search
        );
    }

    public function getTotalCount(): int
    {
        $entityQuery = $this->entityQueryFactory->createQueryFor(new QuerySearch(0));
        $entityManager = $this->ormBuilder->createEntityManager();

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('entityCount', 'entityCount', 'integer');

        $query = $entityManager->createNativeQuery(str_replace(
            'SELECT DISTINCT entity.*',
            'SELECT COUNT(entity.id) AS entityCount',
            $entityQuery->getWithoutPagination()
        ), $rsm);
        $result = $query->execute(hydrationMode: AbstractQuery::HYDRATE_SINGLE_SCALAR);
        return $result ?? 0;
    }
}
