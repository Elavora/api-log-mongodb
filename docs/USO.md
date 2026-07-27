# Guia de uso

`MongoLogExtension` registra o writer MongoDB e o `Logger` do framework.

```php
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Logging\Logger;

$application = Application::create()->extend(new MongoLogExtension([
    'host' => 'mongo',
    'port' => 27017,
    'database' => 'api_logs',
    'collection' => 'application_logs',
    'username' => 'api',
    'password' => 'secret',
]));

$logger = $application->container()->get(Logger::class);
$logger->warning('Fila atrasada', ['queue' => 'emails']);
```

A porta aceita inteiro ou string decimal entre `1` e `65535`; quando ausente, usa `27017`. `username` e `password` devem ser informados juntos e sao percent-encoded. Uma `uri` nao vazia substitui host, porta e credenciais separados.

## Validacao do pacote

A suite definitiva requer a extensao PHP `mongodb`. Execute a partir da raiz do clone:

```bash
docker run --rm -v "${PWD}:/workspace" -w /workspace composer:2 sh -lc '
  apk add --no-cache --virtual .build-deps $PHPIZE_DEPS openssl-dev &&
  pecl install mongodb &&
  docker-php-ext-enable mongodb &&
  composer update --no-interaction --no-progress --prefer-dist &&
  composer check
'
```

Para testes unitarios sem a extensao, a instalacao pode usar `--ignore-platform-req=ext-mongodb`; isso nao substitui a validacao definitiva.
