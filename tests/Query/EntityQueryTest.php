<?php
namespace Apie\Tests\DoctrineEntityDatalayer\Query;

use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\Search\QuerySearch;
use Apie\DoctrineEntityDatalayer\Query\EntityQuery;
use Apie\DoctrineEntityDatalayer\Query\FieldTextSearchFilter;
use Apie\DoctrineEntityDatalayer\Query\FulltextSearchFilter;
use Apie\Fixtures\Entities\Order;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class EntityQueryTest extends TestCase
{
    use ProphecyTrait;
    private function createFakeManager(): EntityManagerInterface
    {
        $connection = $this->prophesize(Connection::class);
        $connection->quote(Argument::type('string'))->willReturnArgument(0);

        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->getConnection()->willReturn($connection->reveal());
        return $manager->reveal();
    }
    /**
     * @test
     * @dataProvider sqlProvider
     */
    public function it_can_create_sql_with_text_search(string $expectedOutputPath, QuerySearch $querySearch): void
    {
        $testItem = new EntityQuery(
            $this->createFakeManager(),
            new ReflectionClass(Order::class),
            new BoundedContextId('test'),
            $querySearch,
            new FulltextSearchFilter(new ReflectionClass(Order::class), new BoundedContextId('test')),
            new FieldTextSearchFilter('name', 'apie_name'),
            new FieldTextSearchFilter('value', 'apie_value'),
        );
        $actual = str_replace("\r", '', $testItem->__toString());
        // file_put_contents($expectedOutputPath, $actual);
        $expected = str_replace("\r", '', file_get_contents($expectedOutputPath));
        $this->assertEquals($expected, $actual);
    }

    public function sqlProvider(): Generator
    {
        foreach (Finder::create()->in(__DIR__ . '/../../fixtures/entity-query')->files()->name('*.json') as $inputFile) {
            $input = (array) json_decode(file_get_contents($inputFile), true);
            $querySearch = QuerySearch::fromArray($input);
            yield $inputFile->getBasename('.json') => [str_replace('.json', '.sql', (string) $inputFile), $querySearch];
        }
    }
}
