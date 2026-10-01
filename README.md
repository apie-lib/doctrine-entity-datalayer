<img src="https://raw.githubusercontent.com/apie-lib/apie-lib-monorepo/main/docs/apie-logo.svg" width="100px" align="left" />
<h1>doctrine-entity-datalayer</h1>






 [![Latest Stable Version](https://poser.pugx.org/apie/doctrine-entity-datalayer/v)](https://packagist.org/packages/apie/doctrine-entity-datalayer) [![Total Downloads](https://poser.pugx.org/apie/doctrine-entity-datalayer/downloads)](https://packagist.org/packages/apie/doctrine-entity-datalayer) [![Latest Unstable Version](https://poser.pugx.org/apie/doctrine-entity-datalayer/v/unstable)](https://packagist.org/packages/apie/doctrine-entity-datalayer) [![License](https://poser.pugx.org/apie/doctrine-entity-datalayer/license)](https://packagist.org/packages/apie/doctrine-entity-datalayer) [![PHP Composer](https://apie-lib.github.io/projectCoverage/coverage-doctrine-entity-datalayer.svg)](https://apie-lib.github.io/projectCoverage/doctrine-entity-datalayer/index.html)  

[![PHP Composer](https://github.com/apie-lib/doctrine-entity-datalayer/actions/workflows/php.yml/badge.svg?event=push)](https://github.com/apie-lib/doctrine-entity-datalayer/actions/workflows/php.yml)

This package is part of the [Apie](https://github.com/apie-lib) library.
The code is maintained in a monorepo, so PR's need to be sent to the [monorepo](https://github.com/apie-lib/apie-lib-monorepo/pulls)

## Documentation
Doctrine ORM datalayer for Apie entities, on top of `apie/doctrine-entity-converter`. It provides `Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayer`, query filters for searching/sorting/paging, and search-index (re)indexing strategies.

### Standalone usage
Install it with:
```bash
composer require apie/doctrine-entity-datalayer
```

Build `Apie\DoctrineEntityDatalayer\OrmBuilder` (wrapping the converter's `OrmBuilder`) and pass it, a `Apie\StorageMetadata\DomainToStorageConverter`, an `Apie\DoctrineEntityDatalayer\IndexStrategy\IndexStrategyInterface`, and a `Apie\DoctrineEntityDatalayer\Factories\DoctrineListFactory` into `DoctrineEntityDatalayer`. Register it as a `apie.datalayer` so Apie's actions use Doctrine for the bounded context.

### Symfony integration
Via `apie/apie-bundle`, `doctrine_entity_datalayer.yaml` is loaded automatically and registers the datalayer, the `EntityReindexer`, and both index strategies. Configuration keys `apie.doctrine.build_once`, `apie.doctrine.run_migrations` and `apie.doctrine.connection_params` (under `config/packages/apie.yaml`) configure the underlying `OrmBuilder`.

### Laravel integration
Via `apie/laravel-apie`, the generated `Apie\DoctrineEntityDatalayer\DoctrineEntityDatalayerServiceProvider` is auto-registered and wires the same datalayer, query factories, and reindexing services into the Laravel container.
