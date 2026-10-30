# Guia de uso

Pacote opcional de logs em MongoDB para o framework Elavora 2.

Este guia é a especificação do pacote. Tudo o que está aqui é comportamento garantido e pode ser coberto por teste. A
migração a partir da `0.x` está em [Versão e compatibilidade](#versão-e-compatibilidade).

## Instalação

```bash
composer require elavora/api-log-mongodb
```

## Dependências de runtime

| Dependência             | Versão  |
|-------------------------|---------|
| `php`                   | `>=8.3` |
| `ext-mongodb`           | `*`     |
| `elavora/api-framework` | `^2.0`  |

O pacote não aceita mais o framework `0.x` nem o `1.x`. A versão mínima do PHP acompanha o framework 2, que exige
PHP 8.3.

A extensão `mongodb` só é carregada de fato quando o pacote grava num servidor (`MongoLogWriter::connect()`). Os testes
do pacote não conectam em nenhum servidor MongoDB e não precisam da extensão carregada para rodar. O `composer install`,
porém, exige a extensão (`ext-mongodb`), e a imagem de desenvolvimento já a traz
([Desenvolvimento e testes](DESENVOLVIMENTO.md#extensão-mongodb)).

## API pública

Namespace: `Elavora\Api\Extension\LogMongoDb`.

### `MongoLogConfig`

```php
final class MongoLogConfig
{
    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self;
    public function uri(): string;
    public function database(): string;
    public function collection(): string;
    public function collectionNamespace(): string;
}
```

O construtor é privado: use `fromArray()`. O objeto é imutável.

| Método                  | Devolve                                                      |
|-------------------------|--------------------------------------------------------------|
| `uri()`                 | a URI de conexão (informada ou montada)                      |
| `database()`            | o banco onde os logs são gravados                            |
| `collection()`          | a coleção (`logs` quando não informada)                      |
| `collectionNamespace()` | `database.collection`, por exemplo `api_logs.application_logs` |

### `MongoLogWriter`

```php
final class MongoLogWriter implements \Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter
{
    public function __construct(MongoLogConfig $config, callable $insertDocument);
    public static function connect(MongoLogConfig $config): self;
    /** @param array<string, mixed> $entry */
    public function write(array $entry): void;
}
```

- `write($entry)` chama `$insertDocument($config->collectionNamespace(), $entry)`. O documento vai exatamente como
  recebido, sem acrescentar, remover nem renomear campos.
- `$insertDocument` tem a forma `function (string $namespace, array $entry): void`. Os testes passam uma função própria
  e por isso não precisam de servidor.
- `connect($config)` devolve um writer que cria um `MongoDB\Driver\Manager` com `$config->uri()` e grava cada entrada
  com `MongoDB\Driver\BulkWrite::insert()` e `WriteConcern::MAJORITY` com tempo limite de 1000 ms. Esse é o único
  caminho que usa o driver e o servidor. Sem a extensão `mongodb` carregada, lança `RuntimeException` com a
  mensagem `A extensao PHP mongodb nao esta disponivel.`.

### `Contracts\LogWriter`

```php
interface LogWriter extends \Elavora\Api\Framework\Contracts\LogWriter
{
}
```

Contrato local, sem métodos próprios. Herda `write(array $entry): void` do contrato do framework 2.
`MongoLogWriter` o implementa.

### `MongoLogExtension`

```php
final class MongoLogExtension implements \Elavora\Api\Framework\Contracts\Extension
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config, ?callable $writerFactory = null);
    public function register(\Elavora\Api\Framework\Application $application): void;
}
```

- O construtor valida `$config` na hora, com `MongoLogConfig::fromArray()`: configuração inválida lança
  `InvalidArgumentException` já no `new`, e não no `register()`.
- `$writerFactory` é opcional (testes ou integração própria). Tem a forma `function (MongoLogConfig $config):
  \Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter`. Sem ela, o writer vem de `MongoLogWriter::connect()`.
- `register()` liga três tipos no container da aplicação:

  | Tipo ligado                                          | Resolve para                                   |
  |------------------------------------------------------|------------------------------------------------|
  | `Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter` | o writer (da fábrica ou de `connect()`)        |
  | `Elavora\Api\Framework\Contracts\LogWriter`          | o mesmo writer do tipo local                   |
  | `Elavora\Api\Framework\Logging\Logger`               | `new Logger($writer)`                          |

- O writer só é criado na primeira resolução, e não no `register()`: registrar a extensão não conecta no MongoDB nem
  exige a extensão `mongodb`. O container do framework guarda a instância depois da primeira resolução, então duas
  chamadas a `container()->get(...)` devolvem o mesmo objeto, e o tipo local e o tipo do framework devolvem o
  mesmo writer.

## Configuração

| Opção        | Tipo     | Padrão  | Uso                                                              |
|--------------|----------|---------|------------------------------------------------------------------|
| `database`   | `string` | —       | **obrigatória**: banco dos logs                                  |
| `collection` | `string` | `logs`  | coleção dos logs                                                 |
| `uri`        | `string` | —       | URI completa; quando informada, `host`, `port`, `username` e `password` são ignorados |
| `host`       | `string` | —       | obrigatória quando não há `uri`                                  |
| `port`       | `int\|string` | `27017` | porta, usada só quando a URI é montada                       |
| `username`   | `string` | —       | usuário, usado só quando a URI é montada                         |
| `password`   | `string` | —       | senha, usada só quando a URI é montada                           |

### Normalização

- Uma opção de texto é aceita só se for `string`. Ela passa por `trim()`, e texto vazio ou só com espaços conta como
  ausente. Valores de outros tipos (`int`, `null`, `array`...) contam como ausentes. Exemplo: `'database' => '  api_logs  '`
  vira `api_logs`; `'database' => 123` conta como ausente.
- `port`: um inteiro maior que zero vira o texto desse número (`27018` vira `'27018'`); uma `string` não vazia é usada
  como está depois do `trim()` (`' 27018 '` vira `'27018'`; o texto não é validado como número); qualquer outra
  coisa (ausente, `0`, negativo, `null`) vira `'27017'`.
- Chaves desconhecidas em `$config` são ignoradas.

### Como a URI é montada

1. Se `uri` está presente, ela é usada como está, depois do `trim()`.
2. Senão, o `host` é obrigatório. A URI é `mongodb://` + credenciais + `host` + `:` + porta.
3. As credenciais só existem se houver `username` ou `password`. O formato é `usuário:senha@`, com os dois valores
   codificados por `rawurlencode()`. Se só um deles existir, o outro entra como texto vazio.

| Entrada (`host`, `port`, `username`, `password`)            | `uri()`                                  |
|-------------------------------------------------------------|------------------------------------------|
| `mongo`, —, —, —                                            | `mongodb://mongo:27017`                  |
| `mongo`, `27018`, `api`, `secret value`                     | `mongodb://api:secret%20value@mongo:27018` |
| `mongo`, `'27018'`, `api`, —                                | `mongodb://api:@mongo:27018`             |
| `mongo`, —, —, `s3`                                         | `mongodb://:s3@mongo:27017`              |
| `mongo`, `0`, —, —                                          | `mongodb://mongo:27017`                  |
| `uri` = `mongodb://x:1`, `host` = `outro`                   | `mongodb://x:1` (o `host` é ignorado)    |

### Erros

Todos são `InvalidArgumentException`, lançados por `MongoLogConfig::fromArray()` (e, por isso, pelo construtor de
`MongoLogExtension`). As mensagens são exatamente estas:

| Situação                                  | Mensagem                                                                      |
|-------------------------------------------|-------------------------------------------------------------------------------|
| sem `database` válido                     | `O banco MongoDB de logs e obrigatorio.`                                      |
| sem `uri` e sem `host` válido             | `O host MongoDB de logs e obrigatorio quando a URI nao e informada.`          |

O banco é conferido antes do host: sem nenhum dos dois, a mensagem é a do banco.

## Documento gravado

O `Logger` do framework 2 monta a entrada e a passa para o writer, que a grava sem mudar nada:

```php
[
    'timestamp' => gmdate('c'),   // por exemplo '2026-05-27T12:00:00+00:00'
    'level' => 'info',            // debug, info, warning ou error
    'message' => 'Requisicao recebida',
    'request_id' => '...',
    'context' => ['route' => '/health'],
]
```

`exception($e, $context)` grava nível `error`, mensagem `Unhandled exception` e, em `context`, a chave `exception`
com `class` e `code` (a mensagem da exceção não é copiada, para não vazar dado sensível).

Exemplo completo:

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
// grava em api_logs.application_logs
```

Com uma fábrica própria (sem servidor), como nos testes:

```php
use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Extension\LogMongoDb\MongoLogWriter;

$documentos = [];
$extension = new MongoLogExtension(
    ['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs'],
    static fn (MongoLogConfig $config): LogWriter => new MongoLogWriter(
        $config,
        static function (string $namespace, array $entry) use (&$documentos): void {
            $documentos[] = [$namespace, $entry]; // 'api_logs.logs'
        },
    ),
);
```

## Uso pelo bootstrap do framework 2

O framework 2 carrega a extensão sozinho a partir do ambiente, por meio de
`Elavora\Api\Framework\Bootstrap\EnvironmentExtensions` e `ApplicationBootstrap`. Com `LOG_DRIVER=mongodb`, ele cria
`new MongoLogExtension($config)` com:

| Opção do pacote | Variável de ambiente    | Padrão do framework |
|-----------------|-------------------------|---------------------|
| `uri`           | `MONGO_LOG_URI`         | `null`              |
| `host`          | `MONGO_LOG_HOST`        | `mongo`             |
| `port`          | `MONGO_LOG_PORT`        | `27017`             |
| `database`      | `MONGO_LOG_DATABASE`    | `api_logs`          |
| `collection`    | `MONGO_LOG_COLLECTION`  | `logs`              |
| `username`      | `MONGO_LOG_USERNAME`    | `null`              |
| `password`      | `MONGO_LOG_PASSWORD`    | `null`              |

Por isso o construtor da extensão precisa continuar recebendo um único array (o segundo argumento é opcional). O
framework lança `RuntimeException` com `Instale elavora/api-log-mongodb para usar LOG_DRIVER=mongodb.` quando o pacote
não está instalado, e com `LOG_DRIVER deve ser stdout, file ou mongodb.` para um valor desconhecido.

Quem registra a extensão pelo ambiente não deve registrá-la de novo na lista `extensions` do bootstrap. Registre só um
destino de log por aplicação, porque os pacotes de log usam o mesmo contrato `Elavora\Api\Framework\Contracts\LogWriter`
e o mesmo `Logger`.

## Versão e compatibilidade

A versão `1.0` exige PHP `>=8.3` e `elavora/api-framework` `^2.0`. Antes (`0.x`), o pacote aceitava PHP `>=8.1` e
`elavora/api-framework` `^0.3.1`. Para atualizar a partir da `0.2.0`, troque as restrições no `composer.json` do seu
projeto e rode `composer update elavora/api-framework elavora/api-log-mongodb`:

```json
{
  "require": {
    "php": ">=8.3",
    "elavora/api-framework": "^2.0",
    "elavora/api-log-mongodb": "^1.0"
  }
}
```

A API do pacote não muda: `MongoLogConfig`, `MongoLogWriter`, `MongoLogExtension` e `Contracts\LogWriter` mantêm
namespace, assinaturas e comportamento, e o documento gravado segue o formato legado. Só a dependência do framework
sobe de major (e o PHP mínimo sobe para 8.3). O `Contracts\LogWriter` estende o
`Elavora\Api\Framework\Contracts\LogWriter` do framework 2, e a extensão usa o `Elavora\Api\Framework\Application`, o
`Elavora\Api\Framework\Contracts\Extension` e o `Elavora\Api\Framework\Logging\Logger` do framework 2.

Comportamento atual incorreto, como contraexemplo: no framework `0.x` que a `0.2.0` resolve, o tipo
`Elavora\Api\Framework\Contracts\LogWriter` não existe, e 5 dos 9 testes falham com
`Interface "Elavora\Api\Framework\Contracts\LogWriter" not found`. A causa é a dependência: com o framework 2.0, a
suíte passa inteira sem mudar o código do pacote. Por isso o pacote `0.x` não funciona com o framework 2, nem a `1.0`
com o `0.x`: atualize os dois juntos.

O `extra.branch-alias` do pacote passa de `0.1-dev` para `1.0-dev` (`dev-main`).

Correspondência com a API do runtime legado:

| API legada | Comportamento preservado no pacote |
|------------|------------------------------------|
| `Elavora\Api\Integration\Database\MongoDatabase` | A configuração aceita `uri` ou `host`, `port`, `username`, `password` e `database`; a escrita é um documento numa coleção MongoDB. |
| `Elavora\Api\Core\Logger` | `Elavora\Api\Framework\Logging\Logger` gera o documento com `timestamp`, `level`, `message`, `request_id` e `context`. |
| `Elavora\Api\Interface\NoSqlDatabase` | Continua sendo contrato do runtime legado; o pacote não o substitui nem exige alteração nele. |

Os valores de `Settings::getSettingsMongo()` podem ser passados direto a `MongoLogConfig::fromArray()`, e o valor legado
de `BFR_API_LOG_COLLECTION` corresponde à opção `collection`.

## Validação local

Os testes rodam no container `ghcr.io/elavora/php-dev:8.3-latest`. Essa imagem (a partir da 0.4.0) já traz a extensão
`mongodb`, então não é preciso instalar nada. Rode no PowerShell, na raiz do repo, depois de commitar (o clone pega só
o que está commitado):

```powershell
docker run --rm -v "$($PWD.Path):/origem:ro" ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory "*" && git clone -q /origem /tmp/repo && cd /tmp/repo && composer install --no-interaction --prefer-dist && composer validate --strict --no-check-publish && composer test'
```

O comando clona `/origem` em `/tmp/repo` (para não gravar `vendor/` no seu repositório), instala as dependências,
valida o `composer.json` e roda os testes. Nenhum teste precisa de servidor MongoDB: não há teste para pular por falta
dele. O CI completo (formatação, lint, PHPStan e cobertura) está em
[Desenvolvimento e testes](DESENVOLVIMENTO.md#validar-como-o-ci).

## Observações

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensões no bootstrap da aplicação.
- Instale apenas os módulos que a aplicação realmente usa.
