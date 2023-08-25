<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Entities\EntityInterface;
use Apie\Core\Persistence\Lists\PersistenceFieldList;
use Apie\Core\Persistence\Metadata\EntityMetadata;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityConverter\OrmBuilder as DoctrineEntityConverterOrmBuilder;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
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
        private readonly bool $buildOnce,
        private readonly bool $runMigrations,
        private readonly bool $devMode,
        private readonly ?string $proxyDir,
        private readonly ?CacheItemPoolInterface $cache,
        private readonly string $path,
        private readonly array $connectionConfig,
        private readonly ?EventManager $eventManager
    ) {

    }

    public function getGeneratedNamespace(): string
    {
        return 'Generated\\';
    }

    protected function runMigrations(EntityManagerInterface $entityManager): void
    {
        $tool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $sql = $tool->getDropDatabaseSQL();
        foreach ($sql as $statement) {
            $entityManager->getConnection()->exec($statement);
        }
        $sql = $tool->getUpdateSchemaSql($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->exec($statement);
        }
    }

    /**
     * @param ReflectionClass<EntityInterface> $class
     * @return ReflectionClass<GeneratedDoctrineEntityInterface>
     */
    public function toDoctrineClass(ReflectionClass $class, ?BoundedContext $boundedContext = null): ReflectionClass
    {
        $manager = $this->createEntityManager();
        if ($boundedContext) {
            $metadata = new EntityMetadata(
                $boundedContext->getId(),
                $class->name,
                new PersistenceFieldList()
            );
            $manager->getMetadataFactory()->getAllMetadata();
            return new ReflectionClass($this->getGeneratedNamespace() . $metadata->getName());
        }
        foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $refl = new ReflectionClass($metadata->getName());
            if ($refl->hasMethod('getOriginalClassName')) {
                $originalClass = $refl->getMethod('getOriginalClassName')->invoke(null);
                if ($originalClass === $class->name) {
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
            return false;
        }
        $di = new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS);
        return iterator_count($di) === 0;
    }

    public function createEntityManager(): EntityManagerInterface
    {
        if (!$this->buildOnce || $this->isEmptyPath()) {
            $this->ormBuilder->createOrm($this->path);
        }
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [$this->path],
            $this->devMode,
            $this->proxyDir,
            $this->cache
        );
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
