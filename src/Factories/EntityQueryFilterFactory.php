<?php

namespace Apie\DoctrineEntityDatalayer\Factories;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Context\ApieContext;
use Apie\Core\Entities\RequiresRecalculatingInterface;
use Apie\Core\Enums\ScalarType;
use Apie\Core\Metadata\MetadataFactory;
use Apie\Core\Permissions\RequiresPermissionsInterface;
use Apie\DoctrineEntityDatalayer\Query\EntityQueryFilterInterface;
use Apie\DoctrineEntityDatalayer\Query\FieldTextSearchFilter;
use Apie\DoctrineEntityDatalayer\Query\FulltextSearchFilter;
use Apie\DoctrineEntityDatalayer\Query\OrderBySearchFilter;
use Apie\DoctrineEntityDatalayer\Query\RequiresPermissionFilter;
use Apie\DoctrineEntityDatalayer\Query\SearchByRequireUpdateFilter;
use Apie\StorageMetadata\Attributes\GetMethodAttribute;
use Apie\StorageMetadata\Attributes\GetSearchIndexAttribute;
use Apie\StorageMetadata\Attributes\PropertyAttribute;
use Apie\StorageMetadata\Interfaces\StorageDtoInterface;
use Apie\TypeConverter\ReflectionTypeFactory;
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

        if (in_array(RequiresRecalculatingInterface::class, $domainClass->getInterfaceNames())) {
            $filters[] = new SearchByRequireUpdateFilter();
        }

        foreach ($doctrineClass->getProperties(ReflectionProperty::IS_PUBLIC) as $publicProperty) {
            if (str_starts_with($publicProperty->name, 'search_')) {
                foreach ($publicProperty->getAttributes(GetSearchIndexAttribute::class) as $publicPropertyAttribute) {
                    $filters[] = new FieldTextSearchFilter(
                        substr($publicProperty->name, strlen('search_')),
                        $publicPropertyAttribute->newInstance()->arrayValueType ?? (string) $publicProperty->getType()
                    );
                }
            } else {
                foreach ($publicProperty->getAttributes(GetMethodAttribute::class) as $publicPropertyAttribute) {
                    $filters[] = new FieldTextSearchFilter(
                        $publicPropertyAttribute->newInstance()->methodName,
                        $publicProperty->name
                    );
                }
                $metadata = MetadataFactory::getModificationMetadata(
                    $publicProperty->getType() ?? ReflectionTypeFactory::createReflectionType('mixed'),
                    new ApieContext()
                );
                if (in_array($metadata->toScalarType(true), ScalarType::PRIMITIVES)) {
                    foreach ($publicProperty->getAttributes(PropertyAttribute::class) as $publicPropertyAttribute) {
                        $filters[] = new OrderBySearchFilter(
                            $publicPropertyAttribute->newInstance()->propertyName,
                            $publicProperty->getName()
                        );
                    }
                }
            }
        }

        if (in_array(RequiresPermissionsInterface::class, $domainClass->getInterfaceNames())) {
            $filters[] = new RequiresPermissionFilter($domainClass, $boundedContextId);
        }

        return $filters;
    }
}
