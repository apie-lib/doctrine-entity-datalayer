<?php
namespace Apie\DoctrineEntityDatalayer\Query;

interface FieldSearchFilterInterface extends EntityQueryFilterInterface
{
    public function getFilterName(): string;
}
