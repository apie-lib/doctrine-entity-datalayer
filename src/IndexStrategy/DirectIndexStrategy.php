<?php
namespace Apie\DoctrineEntityDatalayer\IndexStrategy;

use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityDatalayer\EntityReindexer;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;

class DirectIndexStrategy implements IndexStrategyInterface
{
    public function __construct(private readonly EntityReindexer $entityReindexer)
    {
    }

    public function updateIndex(
        HasIndexInterface $doctrineEntity,
        EntityInterface $entity
    ): void {
        $this->entityReindexer->updateIndex($doctrineEntity, $entity);
    }
}
