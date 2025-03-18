<?php

namespace Apie\DoctrineEntityDatalayer\Commands;

use Apie\DoctrineEntityDatalayer\EntityReindexer;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Apie\StorageMetadataBuilder\Interfaces\HasIndexInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ApieUpdateIdfCommand extends Command
{
    public function __construct(
        private readonly EntityReindexer $entityReindexer,
        private readonly OrmBuilder $ormBuilder
    ) {
        parent::__construct('apie:update-idf');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->ormBuilder->createEntityManager()->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->reflClass && in_array(HasIndexInterface::class, $metadata->reflClass->getInterfaceNames())) {
                $output->writeln($metadata->reflClass->getMethod('getClassReference')->invoke(null)->getShortName());
                $this->entityReindexer->recalculateIdfForAll($metadata->reflClass);
            }
        }
        return Command::SUCCESS;
    }
}
