<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Entities\EntityInterface;
use Apie\DoctrineEntityConverter\OrmBuilder as DoctrineEntityConverterOrmBuilder;
use Apie\DoctrineEntityDatalayer\Exceptions\CouldNotUpdateDatabaseAutomatically;
use Apie\StorageMetadata\Interfaces\StorageDtoInterface;
use Apie\StorageMetadataBuilder\Interfaces\RootObjectInterface;
use Doctrine\Bundle\DoctrineBundle\Middleware\DebugMiddleware;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FilesystemIterator;
use Psr\Cache\CacheItemPoolInterface;
use RecursiveDirectoryIterator;
use ReflectionClass;
use RuntimeException;

class OrmBuilder
{
    private ?EntityManagerInterface $createdEntityManager = null;
    /**
     * @param array<string, mixed> $connectionConfig
     */
    public function __construct(
        private readonly DoctrineEntityConverterOrmBuilder $ormBuilder,
        private bool $buildOnce,
        private bool $runMigrations,
        private readonly bool $devMode,
        private readonly ?string $proxyDir,
        private readonly ?CacheItemPoolInterface $cache,
        private readonly string $path,
        private readonly array $connectionConfig,
        private readonly ?EventManager $eventManager = null,
        private readonly ?DebugMiddleware $debugMiddleware = null
    ) {
    }

    public function getGeneratedNamespace(): string
    {
        return 'Generated\\ApieEntities\\';
    }

    protected function runMigrations(EntityManagerInterface $entityManager, bool $firstCall = true): void
    {
        $tool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        
        try {
            $sql = $tool->getUpdateSchemaSql($classes);
            foreach ($sql as $statement) {
                $entityManager->getConnection()->executeStatement($statement);
            }
        } catch (DriverException $driverException) {
            if ($firstCall) {
                $sql = $tool->getDropDatabaseSQL();
                foreach ($sql as $statement) {
                    $entityManager->getConnection()->executeStatement($statement);
                }
                $this->runMigrations($entityManager, false);
            }
            throw new CouldNotUpdateDatabaseAutomatically($driverException);
        }
        $this->runMigrations = false;
    }

    /**
     * @param ReflectionClass<EntityInterface> $class
     * @return ReflectionClass<StorageDtoInterface>
     */
    public function toDoctrineClass(ReflectionClass $class, ?BoundedContext $boundedContext = null): ReflectionClass
    {
        $manager = $this->createEntityManager();
        foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $refl = new ReflectionClass($metadata->getName());
            if (in_array(RootObjectInterface::class, $refl->getInterfaceNames())) {
                $originalClass = $refl->getMethod('getClassReference')->invoke(null);
                if ($originalClass->name === $class->name) {
                    return $refl;
                }
            }
        }
        throw new RuntimeException(
            sprintf(
                'Could not find Doctrine class to handle %s',
                $class->name
            )
        );
    }

    private function isEmptyPath(): bool
    {
        if (!file_exists($this->path) || !is_dir($this->path)) {
            return true;
        }
        $di = new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS);
        foreach ($di as $ignored) {
            return false;
        }

        return true;
    }

    public function createEntityManager(): EntityManagerInterface
    {
        if (!$this->buildOnce || $this->isEmptyPath()) {
            $this->ormBuilder->createOrm($this->path);
            $this->buildOnce = true;
        }
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [$this->path],
            $this->devMode,
            $this->proxyDir,
            $this->devMode ? null : $this->cache,
            reportFieldsWhereDeclared: true
        );
        if (class_exists(DefaultSchemaManagerFactory::class) && is_callable([$config, 'setSchemaManagerFactory'])) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }
        $config->setLazyGhostObjectEnabled(true);
        $config->setSchemaAssetsFilter(static function (string|AbstractAsset $assetName): bool {
            if ($assetName instanceof AbstractAsset) {
                $assetName = $assetName->getName();
            }
        
            return (bool) preg_match("~^apie_~i", $assetName);
        });
        if ($this->debugMiddleware) {
            $config->setMiddlewares([
                $this->debugMiddleware
            ]);
        }
        if (!$this->createdEntityManager || !$this->createdEntityManager->isOpen()) {
            $connection = DriverManager::getConnection($this->connectionConfig, $config, $this->eventManager ?? new EventManager());
            $this->createdEntityManager = new EntityManager($connection, $config);
            if ($this->runMigrations) {
                $this->runMigrations($this->createdEntityManager);
            }
        }
        
        return $this->createdEntityManager;
    }
}
