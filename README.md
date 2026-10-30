# elavora/api-log-mongodb

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-log-mongodb.svg?style=flat-square)](https://packagist.org/packages/elavora/api-log-mongodb)
[![Qualidade](https://github.com/Elavora/api-log-mongodb/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-log-mongodb/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-log-mongodb/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-log-mongodb/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-log-mongodb.svg?style=flat-square)](LICENSE)

Pacote opcional de logs em MongoDB para o framework Elavora 2
(`elavora/api-framework` `^2.0`, PHP `>=8.3`).

Ele registra `Elavora\Api\Framework\Logging\Logger` pronto para uso e o contrato local
`Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter`, que recebe documentos de log prontos e os grava na coleção
configurada.

## Uso pelo ambiente

Com o bootstrap do framework 2, basta instalar o pacote e definir `LOG_DRIVER=mongodb`. As variáveis
`MONGO_LOG_URI`, `MONGO_LOG_HOST`, `MONGO_LOG_PORT`, `MONGO_LOG_DATABASE`, `MONGO_LOG_COLLECTION`,
`MONGO_LOG_USERNAME` e `MONGO_LOG_PASSWORD` são opcionais (veja os padrões em [docs/USO.md](docs/USO.md)).

## Registro manual

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
    ->info('Requisição recebida', ['route' => '/health']);
```

Também podem ser informados `host`, `port`, `username` e `password` no lugar de `uri`. A porta padrão é `27017` e a
coleção padrão é `logs`. O banco (`database`) é obrigatório.

O documento gravado segue o formato legado:

```php
[
    'timestamp' => gmdate('c'),
    'level' => 'info',
    'message' => 'Requisição recebida',
    'request_id' => '...',
    'context' => [],
]
```

Os valores produzidos por `Settings::getSettingsMongo()` podem ser passados direto a `MongoLogConfig::fromArray()`. O
valor legado de `BFR_API_LOG_COLLECTION` corresponde à opção `collection` da extensão.

O framework não tem logger central no core. Este pacote é opcional: instale e registre somente quando a aplicação
precisar gravar logs em MongoDB. Registre só um destino de log por aplicação.

Especificação completa, exemplos e regras de configuração: [docs/USO.md](docs/USO.md).

## Instalação

```bash
composer require elavora/api-log-mongodb
```

Requisitos: PHP 8.3 ou superior, a extensão PHP `mongodb` e `elavora/api-framework` `^2.0`.

Versão `1.0`: atualiza o pacote para o `elavora/api-framework` 2.0, que substitui o `^0.3.1`, e sobe o PHP mínimo de
8.1 para 8.3. A API do pacote não muda; quem usa o framework `0.x` precisa atualizar junto (veja o
[guia de migração](docs/USO.md#versão-e-compatibilidade)).

## Desenvolvimento e testes

Você só precisa de Docker. O ambiente é a imagem `ghcr.io/elavora/php-dev:8.3-latest`, que já traz a extensão
`mongodb`: abra o repositório no VS Code com **Dev Containers: Reopen in Container**, ou rode no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

Os testes não precisam de servidor MongoDB. Veja [Extensão mongodb](docs/DESENVOLVIMENTO.md#extensão-mongodb).

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
5. Confira o comentário do pipeline de qualidade no PR. O PR só é integrado com todos os checks verdes.

## Versões

Cada PR integrado na `main` gera uma versão automaticamente, conforme as labels do PR: sem a label `release` sai uma
beta (`1.0.1-beta.1`), com ela sai a estável (`1.0.1`). As tags novas não têm o prefixo `v`, e as notas de cada
GitHub Release são geradas em português a partir dos PRs. Veja [Versões e release](docs/AUTOMACAO.md#versões-e-release).

## Documentação

1. [Guia de uso](docs/USO.md): a especificação do pacote, com a API pública, a configuração e a migração.
2. [Desenvolvimento e testes](docs/DESENVOLVIMENTO.md): ambiente, como rodar os testes e o que roda no CI.
3. [Automação do repositório](docs/AUTOMACAO.md): workflows, Dependabot, estilo de código, labels e versões.

## Licença

MIT
