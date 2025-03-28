<?php
namespace Apie\DoctrineEntityDatalayer\Query;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\IdentifierUtils;
use Apie\CountWords\WordCounter;
use Apie\DoctrineEntityDatalayer\Enums\SortingOrder;
use Apie\DoctrineEntityDatalayer\LikeUtils;
use Doctrine\DBAL\Connection;
use ReflectionClass;

final class FulltextSearchFilter implements TextSearchFilterInterface, AddsJoinFilterInterface
{
    /**
     * @param ReflectionClass<EntityInterface> $entityClass
     */
    public function __construct(
        private readonly ReflectionClass $entityClass,
        private readonly BoundedContextId $boundedContextId
    ) {
    }
    public function createJoinQuery(QuerySearch $querySearch, Connection $connection): string
    {
        $textSearch = $querySearch->getTextSearch();
        assert(null !== $textSearch);
        $words = array_keys(WordCounter::countFromString($textSearch));
        $lastWord = end($words);
        $whereStatement = array_map(
            function (string $word) use ($connection, $lastWord) {
                if ($word === $lastWord) {
                    return 'text LIKE ' . $connection->quote(LikeUtils::toLikeString($word));
                }
                return 'text = ' . $connection->quote($word);
            },
            $words
        );

        return sprintf(
            '%sJOIN (
                SELECT ref_apie_resource__%s_%s_id AS entity_id, SUM(idf * tf) AS accuracy
                FROM apie_index_table
                WHERE %s
                GROUP BY entity_id
            ) subquery ON entity.id = subquery.entity_id',
            empty($words) ? 'LEFT ' : '',
            $this->boundedContextId,
            IdentifierUtils::classNameToUnderscore($this->entityClass),
            empty($words) ? '1' : implode(' OR ', $whereStatement)
        );
    }

    public function getWhereCondition(QuerySearch $querySearch, Connection $connection): string
    {
        return '1';
    }

    public function getOrderByCode(SortingOrder $sortingOrder): string
    {
        return 'MAX(subquery.accuracy) ' . $sortingOrder->value;
    }
}
