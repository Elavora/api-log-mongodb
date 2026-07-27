# api-log-mongodb

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)
[![Composer Quality](https://github.com/Elavora/api-log-mongodb/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-log-mongodb/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-log-mongodb/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-log-mongodb/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)

Writer opcional para persistir logs estruturados em uma collection MongoDB.

## Requisitos

- PHP 8.3 ou superior.
- Extensao PHP `mongodb`.
- `elavora/api-framework` 1.x.

## Instalacao

```bash
composer require elavora/api-log-mongodb
```

## Inicio rapido

```php
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Logging\Logger;

$application = Application::create()->extend(new MongoLogExtension([
    'uri' => 'mongodb://mongo:27017',
    'database' => 'api_logs',
    'collection' => 'application_logs',
]));

$application->container()
    ->get(Logger::class)
    ->info('Requisicao recebida', ['route' => '/health']);
```

A URI explicita tem precedencia. Sem ela, informe `host` e opcionalmente porta e credenciais completas.

## Documentacao

Consulte o [guia de uso](docs/USO.md) para todas as opcoes.
