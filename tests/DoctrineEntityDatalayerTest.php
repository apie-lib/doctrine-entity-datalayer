<?php
namespace Apie\Tests\DoctrineEntityDatalayer;

use Apie\Core\Persistence\PersistenceLayerFactory;
use Apie\Core\Persistence\PersistenceMetadataFactory;
use Apie\DoctrineEntityConverter\EntityBuilder;
use Apie\DoctrineEntityConverter\OrmBuilder as DoctrineEntityConverterOrmBuilder;
use Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer;
use Apie\DoctrineEntityDatalayer\OrmBuilder;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\UserWithAddress;
use Apie\Fixtures\ValueObjects\AddressWithZipcodeCheck;
use PHPUnit\Framework\TestCase;

class DoctrineEntityDatalayerTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_store_a_domain_object_with_auto_migration()
    {
        $tempFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('doctrine-');
        if (!@mkdir($tempFolder)) {
            $this->markTestSkipped('Can not create temp folder ' . $tempFolder);
        }
        try {
            $entityPath = $tempFolder . DIRECTORY_SEPARATOR . 'entities';
            if (!@mkdir($entityPath)) {
                $this->markTestSkipped('Can not create entity folder ' . $entityPath);
            }
            $proxyPath = $tempFolder . DIRECTORY_SEPARATOR . 'proxies';
            if (!@mkdir($proxyPath)) {
                $this->markTestSkipped('Can not create proxy folder ' . $proxyPath);
            }
            $ormBuilder = new DoctrineEntityConverterOrmBuilder(
                EntityBuilder::create('Generated'),
                new PersistenceLayerFactory(PersistenceMetadataFactory::create()),
                BoundedContextFactory::createHashmap(),
                true
            );
            $testItem = new DoctrineEntityDatalayer(
                new OrmBuilder(
                    $ormBuilder,
                    buildOnce: true,
                    runMigrations: true,
                    devMode: true,
                    proxyDir: $proxyPath,
                    cache: null,
                    path: $entityPath,
                    connectionConfig: [
                        'driver' => 'pdo_sqlite',
                        'memory' => true
                    ],
                    eventManager: null
                ),
            );
            $entity = new UserWithAddress(
                AddressWithZipcodeCheck::fromNative([
                    'street' => 'Evergreen Terrace',
                    'streetNumber' => 742,
                    'zipcode' => '11111',
                    'city' => 'Springfield',
                ])
            );
            $idBeforePersist = $entity->getId()->toNative();
            $actual = $testItem->persistNew($entity);
            $this->assertEquals($idBeforePersist, $actual->getId()->toNative());
        } finally {
            system('rm -rf '  . escapeshellarg($tempFolder));
        }
    }
}
