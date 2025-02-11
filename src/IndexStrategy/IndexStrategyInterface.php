<?php
namespace Apie\DoctrineEntityDatalayer\IndexStrategy;

use Apie\Core\Entities\EntityInterface;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;

interface IndexStrategyInterface
{
    public function updateIndex(
        HasIndexInterface $doctrineEntity,
        EntityInterface $entity
    ): void;
}
