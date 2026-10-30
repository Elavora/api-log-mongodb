# elavora/api-log-mongodb

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)
[![Qualidade](https://github.com/Elavora/api-log-mongodb/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-log-mongodb/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-log-mongodb/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-log-mongodb/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-log-mongodb.svg?style=flat-square)](LICENSE)

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

## Instalação

```bash
composer require elavora/api-log-mongodb
```

Requisitos: PHP 8.1 ou superior, a extensão PHP `mongodb` e `elavora/api-framework` `^0.3.1`.

## Desenvolvimento e testes

Você só precisa de Docker. O ambiente é a imagem `ghcr.io/elavora/php-dev:8.3-latest`: abra o repositório no VS Code
com **Dev Containers: Reopen in Container**, ou rode no PowerShell. A imagem não tem a extensão `mongodb`, então o
comando instala a extensão antes (precisa de `-u root`; a instalação leva alguns minutos):

```powershell
docker run --rm -u root --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'pecl install mongodb && docker-php-ext-enable mongodb && git config --global --add safe.directory /workspace && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist && composer check'
```

Alternativa sem compilar a extensão (basta para os testes): `composer install --ignore-platform-req=ext-mongodb`. Veja
[Extensão mongodb](docs/DESENVOLVIMENTO.md#extensão-mongodb).

Scripts do Composer:

```bash
composer check     # lint, testes e PHPStan
composer test      # testes PHPUnit
composer lint      # sintaxe (elavora-standards lint)
composer analyse   # PHPStan, nível 8
```

O código segue o padrão PER-CS, com as regras do pacote `elavora/code-standards`. No devcontainer, o arquivo é
formatado ao salvar; no PR, o CI formata o que faltou e commita a correção (`style: organiza o código`).
Os testes rodam no CI, no workflow **Qualidade**, em todo PR. O passo a passo, o que roda no CI e como reproduzi-lo
estão em [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md).

## Como contribuir

1. Crie uma branch a partir da `main` (`feat/...`, `fix/...` ou `docs/...`).
2. Faça commits em português do Brasil, no padrão Conventional Commits (`feat: ...`, `fix: ...`, `docs: ...`).
3. Rode `composer check` antes de abrir o PR.
4. Abra o PR para a `main`, preencha o template e aplique as labels que definem a versão
   ([Versões e release](docs/AUTOMACAO.md#versões-e-release)).
5. Confira o comentário do pipeline de qualidade no PR. Ele não bloqueia o merge, mas quem revisa olha o resultado.

## Versões

Cada PR integrado na `main` gera uma versão automaticamente, conforme as labels do PR: sem a label `release` sai uma
beta (`0.3.0-beta.1`), com ela sai a estável (`0.3.0`). As tags novas não têm o prefixo `v`, e as notas de cada
GitHub Release são geradas em português a partir dos PRs. Veja [Versões e release](docs/AUTOMACAO.md#versões-e-release).

## Documentação

1. [Guia de uso](docs/USO.md): a especificação do pacote, com a API pública e exemplos.
2. [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md): ambiente, como rodar os testes e o que roda no CI.
3. [Automação do repositório](docs/AUTOMACAO.md): workflows, Dependabot, estilo de código, labels e versões.

## Licença

MIT