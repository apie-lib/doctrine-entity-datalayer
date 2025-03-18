<?php

namespace Apie\DoctrineEntityDatalayer\IndexStrategy;

use Apie\Core\Entities\EntityInterface;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;

class BackgroundIndexStrategy implements IndexStrategyInterface
{

    public function updateIndex(HasIndexInterface $doctrineEntity, EntityInterface $entity): void
    {
        // no-op. A console command does this
    }
}
