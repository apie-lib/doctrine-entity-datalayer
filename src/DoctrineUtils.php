<?php
namespace Apie\DoctrineEntityDatalayer;

use Doctrine\Persistence\Proxy;
use ReflectionClass;
use ReflectionProperty;

final class DoctrineUtils
{
    private array $visited = [];
    private function __construct()
    {
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
            if ($property->isStatic()) {
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
