<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\ServiceProviderGenerator\UseGeneratedMethods;
use Illuminate\Support\ServiceProvider;

/**
 * This file is generated with apie/service-provider-generator from file: doctrine_entity_datalayer.yaml
 * @codeCoverageIgnore
 */
class DoctrineEntityDatalayerServiceProvider extends ServiceProvider
{
    use UseGeneratedMethods;

    public function register()
    {
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\EntityReindexer::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\EntityReindexer(
                    $app->make(\Apie\DoctrineEntityDatalayer\OrmBuilder::class),
                    $app->make(\Apie\Core\Indexing\Indexer::class)
                );
            }
        );
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory(
                    $app->make(\Apie\DoctrineEntityDatalayer\OrmBuilder::class),
                    $app->make(\Apie\DoctrineEntityDatalayer\Factories\EntityQueryFilterFactory::class),
                    $app->make(\Apie\StorageMetadata\DomainToStorageConverter::class)
                );
            }
        );
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\Factories\EntityQueryFilterFactory::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\Factories\EntityQueryFilterFactory(
                
                );
            }
        );
        $this->app->singleton(
            \Apie\StorageMetadata\DomainToStorageConverter::class,
            function ($app) {
                return \Apie\StorageMetadata\DomainToStorageConverter::create(
                    $app->make(\Apie\Core\FileStorage\ChainedFileStorage::class),
                    $app->make(\Apie\Core\Indexing\Indexer::class)
                );
                
            }
        );
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer(
                    $app->make(\Apie\DoctrineEntityDatalayer\OrmBuilder::class),
                    $app->make(\Apie\StorageMetadata\DomainToStorageConverter::class),
                    $app->make(\Apie\DoctrineEntityDatalayer\EntityReindexer::class),
                    $app->make(\Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory::class)
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer::class,
            array(
              0 => 'apie.datalayer',
            )
        );
        $this->app->tag([\Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer::class], 'apie.datalayer');
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\OrmBuilder::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\OrmBuilder(
                    $app->make(\Apie\DoctrineEntityConverter\OrmBuilder::class),
                    $this->parseArgument('%apie.doctrine.build_once%'),
                    $this->parseArgument('%apie.doctrine.run_migrations%'),
                    $this->parseArgument('%kernel.debug%'),
                    $this->parseArgument('%kernel.cache_dir%/apie_proxies'),
                    $app->bound(\Psr\Cache\CacheItemPoolInterface::class) ? $app->make(\Psr\Cache\CacheItemPoolInterface::class) : null,
                    $this->parseArgument('%kernel.cache_dir%/apie_entities'),
                    $this->parseArgument('%apie.doctrine.connection_params%'),
                    $app->bound(\Doctrine\Common\EventManager::class) ? $app->make(\Doctrine\Common\EventManager::class) : null,
                    $app->bound('doctrine.dbal.debug_middleware.default') ? $app->make('doctrine.dbal.debug_middleware.default') : null
                );
            }
        );
        
    }
}
