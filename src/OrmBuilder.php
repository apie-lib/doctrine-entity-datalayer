<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Persistence\Lists\PersistenceFieldList;
use Apie\Core\Persistence\Metadata\EntityMetadata;
use Apie\DoctrineEntityConverter\OrmBuilder as DoctrineEntityConverterOrmBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;
use RuntimeException;

class OrmBuilder
{
    public function __construct(
        private readonly DoctrineEntityConverterOrmBuilder $ormBuilder,
        private readonly bool $buildOnce,
        private readonly bool $runMigrations,
        private readonly bool $devMode,
        private readonly ?string $proxyDir,
        private readonly ?string $cache,
        private readonly string $path,
        private readonly array $connectionConfig
    ) {

    }

    public function getGeneratedNamespace(): string
    {
        return 'Generated\\';
    }

    protected function runMigrations(EntityManagerInterface $entityManager)
    {
        $tool = new SchemaTool($entityManager);
        $classes = $entityManager->getMetadataFactory()->getAllMetadata();
        $sql = $tool->getDropDatabaseSQL($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->exec($statement);
        }
        $sql = $tool->getUpdateSchemaSql($classes);
        foreach ($sql as $statement) {
            $entityManager->getConnection()->exec($statement);
        }
    }

    /**
     * @param ReflectionClass<EntityInterface>
     * @return ReflectionClass<BoundedContext>
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
            return $this->getGeneratedNamespace() . $metadata->getName();
        }
        foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $refl = new ReflectionClass($metadata->getName());
            if ($refl->hasMethod('getOriginalClassName')) {
                $originalClass = $refl->getMethod('getOriginalClassName')->invoke(null);
                if ($originalClass === $class->name) {
                    return new ReflectionClass($originalClass);
                }
            }
        }
        throw new RuntimeException(
            sprintf(
                'Could not find Doctrine class to handle %s',
                get_debug_type($originalClass)
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
        $config = Setup::createAttributeMetadataConfiguration(
            [$this->path],
            $this->devMode,
            $this->proxyDir,
            $this->cache
        );

        $result = EntityManager::create($this->connectionConfig, $config);
        if ($this->runMigrations) {
            $this->runMigrations($result);
        }
        return $result;
    }
}
