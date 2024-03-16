<?php
namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\DoctrineEntityDatalayer\Query\EntityQueryFilterInterface;
use Apie\DoctrineEntityDatalayer\Query\FieldTextSearchFilter;
use Apie\DoctrineEntityDatalayer\Query\FulltextSearchFilter;
use Apie\StorageMetadata\Attributes\GetSearchIndexAttribute;
use Apie\StorageMetadata\Interfaces\StorageDtoInterface;
use ReflectionClass;
use ReflectionProperty;

final class EntityQueryFilterFactory
{
    /**
     * @param ReflectionClass<StorageDtoInterface> $doctrineClass
     * @return EntityQueryFilterInterface[]
     */
    public function createFilterList(ReflectionClass $doctrineClass, BoundedContextId $boundedContextId): array
    {
        $domainClass = $doctrineClass->getMethod('getClassReference')->invoke(null);
        $filters = [
            new FulltextSearchFilter(
                $domainClass,
                $boundedContextId
            )
        ];

        foreach ($doctrineClass->getProperties(ReflectionProperty::IS_PUBLIC) as $publicProperty) {
            if (str_starts_with($publicProperty->name, 'search_')) {
                foreach ($publicProperty->getAttributes(GetSearchIndexAttribute::class) as $publicPropertyAttribute) {
                    $filters[] = new FieldTextSearchFilter(
                        substr($publicProperty->name, strlen('search_')),
                        $publicPropertyAttribute->newInstance()->arrayValueType ?? (string) $publicProperty->getType()
                    );
                }
            }
        }
        return $filters;
    }
}
