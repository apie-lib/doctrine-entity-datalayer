<?php
namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityDatalayer\Query\EntityQueryFilterInterface;
use Apie\DoctrineEntityDatalayer\Query\FieldTextSearchFilter;
use Apie\DoctrineEntityDatalayer\Query\FulltextSearchFilter;
use ReflectionClass;

final class EntityQueryFilterFactory
{
    /**
     * @param ReflectionClass<GeneratedDoctrineEntityInterface> $doctrineClass
     * @return EntityQueryFilterInterface[]
     */
    public function createFilterList(ReflectionClass $doctrineClass, BoundedContextId $boundedContextId): array
    {
        $mapping = $doctrineClass->getMethod('getMapping')->invoke(null);
        $domainClass = $doctrineClass->getMethod('getOriginalClassName')->invoke(null);
        $filters = [
            new FulltextSearchFilter(
                new ReflectionClass($domainClass),
                $boundedContextId
            )
        ];
        foreach ($mapping as $originalProperty => $doctrineProperty) {
            $filters[] = new FieldTextSearchFilter($originalProperty, $doctrineProperty);
        }
        return $filters;
    }
}
