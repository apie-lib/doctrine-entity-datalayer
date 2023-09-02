<?php
namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityDatalayer\Query\EntityQuery;
use Apie\DoctrineEntityDatalayer\Query\EntityQueryFilterInterface;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;

final class EntityQueryFactory
{
    /**
     * @var array<string, EntityQuery>
     */
    private array $alreadyLoaded = [];

    /**
     * @var array<int, EntityQueryFilterInterface>
     */
    private array $filters;
    /**
     * @param ReflectionClass<GeneratedDoctrineEntityInterface> $doctrineEntityClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReflectionClass $doctrineEntityClass,
        private readonly BoundedContextId $boundedContextId,
        EntityQueryFilterInterface... $filters
    ) {
        $this->filters = $filters;
    }

    public function createQueryFor(QuerySearch $querySearch): EntityQuery
    {
        $httpQuery = $querySearch->toHttpQuery();
        if (!isset($this->alreadyLoaded[$httpQuery])) {
            $this->alreadyLoaded[$httpQuery] = $this->doCreateQuery($querySearch);
        }
        return $this->alreadyLoaded[$httpQuery];
    }

    /**
     * @return ReflectionClass<GeneratedDoctrineEntityInterface>
     */
    public function getDoctrineClass(): ReflectionClass
    {
        return $this->doctrineEntityClass;
    }

    /**
     * @return ReflectionClass<EntityInterface>
     */
    private function getOriginalClass(): ReflectionClass
    {
        return new ReflectionClass($this->doctrineEntityClass->getMethod('getOriginalClassName')->invoke(null));
    }

    private function doCreateQuery(QuerySearch $querySearch): EntityQuery
    {
        return new EntityQuery(
            $this->entityManager,
            $this->getOriginalClass(),
            $this->boundedContextId,
            $querySearch,
            ...$this->filters
        );
    }
}
