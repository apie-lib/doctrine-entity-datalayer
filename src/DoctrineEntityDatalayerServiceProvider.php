<?php
namespace Apie\DoctrineEntityDatalayer;

use Apie\ServiceProviderGenerator\UseGeneratedMethods;
use Illuminate\Support\ServiceProvider;

/**
 * This file is generated with apie/service-provider-generator from file: doctrine_entity_datalayer.yaml
 * @codecoverageIgnore
 */
class DoctrineEntityDatalayerServiceProvider extends ServiceProvider
{
    use UseGeneratedMethods;

    public function register()
    {
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer(
                    $app->make(\Apie\DoctrineEntityDatalayer\OrmBuilder::class)
                );
            }
        );
        $this->app->singleton(
            \Apie\DoctrineEntityDatalayer\OrmBuilder::class,
            function ($app) {
                return new \Apie\DoctrineEntityDatalayer\OrmBuilder(
                    $app->make(\Apie\DoctrineEntityConverter\OrmBuilder::class),
                    $this->parseArgument('%apie.doctrine.build_once%'),
                    $this->parseArgument('%apie.doctrine.run_migrations%'),
                    $this->parseArgument('%kernel.debug%'),
                    $this->parseArgument('%kernel.cache_dir%/apie_proxies'),
                    $this->parseArgument('%kernel.cache_dir%/apie_datalayer'),
                    $this->parseArgument('%kernel.cache_dir%/apie_entities'),
                    $this->parseArgument('%apie.doctrine.connection_config%')
                );
            }
        );
        
    }
}
