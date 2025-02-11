<?php
namespace Apie\DoctrineEntityDatalayer\IndexStrategy;

use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityDatalayer\EntityReindexer;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class IndexAfterResponseIsSentStrategy implements IndexStrategyInterface, EventSubscriberInterface
{
    /** @var array<int, array{0: HasIndexInterface, 1: EntityInterface}> $todo */
    private array $todo = [];
    public function __construct(private readonly EntityReindexer $entityReindexer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => ['onKernelTerminate']];
    }

    public function updateIndex(
        HasIndexInterface $doctrineEntity,
        EntityInterface $entity
    ): void {
        $this->todo[] = [$doctrineEntity, $entity];
    }

    public function onKernelTerminate(): void
    {
        while (!empty($this->todo)) {
            $call = array_shift($this->todo);
            $this->entityReindexer->updateIndex(
                ...[
                    ...$call,
                    !empty($this->todo)
                ]
            );
        }
    }
}
