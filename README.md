# elavora/api-log-mongodb

Pacote opcional de logs em MongoDB para o framework Elavora.
Ele registra `Elavora\Api\Framework\Logging\Logger` pronto para uso e o contrato local
`Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter`, que recebe documentos de log
prontos e os grava na colecao configurada.

```php
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Framework\Logging\Logger;

$application->extend(new MongoLogExtension([
    'uri' => 'mongodb://mongo:27017',
    'database' => 'api_logs',
    'collection' => 'application_logs',
]));

$application->container()
    ->get(Logger::class)
    ->info('Requisicao recebida', ['route' => '/health']);
```

Tambem podem ser informados `host`, `port`, `username` e `password` no lugar
de `uri`. A porta padrao e `27017` e a colecao padrao e `logs`.

O documento gravado segue o formato legado:

```php
[
    'timestamp' => gmdate('c'),
    'level' => 'info',
    'message' => 'Requisicao recebida',
    'request_id' => '...',
    'context' => [],
]
```

## Compatibilidade

| API existente | Comportamento preservado no pacote |
| --- | --- |
| `Elavora\Api\Integration\Database\MongoDatabase` | Configuracao aceita `uri` ou `host`, `port`, `username`, `password` e `database`; a escrita continua sendo um documento em uma colecao MongoDB. |
| `Elavora\Api\Core\Logger` | `Elavora\Api\Framework\Logging\Logger` gera documento com `timestamp`, `level`, `message`, `request_id` e `context`. |
| `Elavora\Api\Interface\NoSqlDatabase` | Permanece contrato do runtime legado; este pacote nao o substitui nem exige alteracao nele. |

Os valores produzidos por `Settings::getSettingsMongo()` podem ser passados
diretamente a `MongoLogConfig::fromArray()`. O valor legado de
`BFR_API_LOG_COLLECTION` corresponde a opcao `collection` da extensao.

O framework modular nao possui logger central no core. Este pacote e opcional:
instale e registre somente quando a aplicacao precisar gravar logs em MongoDB.
