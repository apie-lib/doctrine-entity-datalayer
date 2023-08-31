<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;

interface OrderByFilterInterface extends EntityQueryFilterInterface
{
    public function getOrderByCode(SortingOrder $sortingOrder): string;
}
