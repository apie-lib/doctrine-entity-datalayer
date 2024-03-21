<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\StorageMetadata\Attributes\GetSearchIndexAttribute;
use Apie\StorageMetadata\Attributes\OneToManyAttribute;
use Doctrine\Persistence\Proxy;
use ReflectionClass;
use ReflectionProperty;

final class DoctrineUtils
{
    /**
     * @var array<string|int, object>
     */
    private array $visited = [];
    private function __construct()
    {
    }

    private function hasOneToManyWithProperty(ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes(OneToManyAttribute::class) as $oneToManyAttribute) {
            if ($oneToManyAttribute->newInstance()->propertyName === null) {
                return false;
            }
        }
        return true;
    }

    private function loadAll(object $entity): void
    {
        $hash = spl_object_hash($entity);
        if (isset($this->visited[$hash])) {
            return;
        }
        $this->visited[$hash] = $entity;
        if ($entity instanceof Proxy) {
            $entity->__load();
        }
        foreach ((new ReflectionClass($entity))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || !$property->isInitialized($entity)) {
                continue;
            }
            $attributes = $property->getAttributes(GetSearchIndexAttribute::class);
            if (!empty($attributes) || !$this->hasOneToManyWithProperty($property)) {
                continue;
            }
            

            $propertyValue = $property->getValue($entity);
            if (is_object($propertyValue)) {
                $this->loadAll($propertyValue);
            }
        }
    }

    public static function loadAllProxies(object $entity): void
    {
        $worker = new self();
        $worker->loadAll($entity);
    }
}
