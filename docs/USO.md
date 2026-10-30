# Guia de uso

Pacote opcional de logs em MongoDB para o framework Elavora.

## Instalacao

```bash
composer require elavora/api-log-mongodb
```

## Quando usar

- Enviar logs para stdout, arquivo ou MongoDB sem trocar a aplicacao.
- Registrar um writer compativel com o logger do framework.
- Padronizar observabilidade por ambiente.

## Exemplo rapido

```php
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;

$application->extend(new MongoLogExtension([
    // Ajuste as opcoes conforme o destino de log escolhido.
]));
```

## Principais pontos de entrada

- `Elavora\Api\Extension\LogMongoDb\MongoLogConfig`
- `Elavora\Api\Extension\LogMongoDb\MongoLogExtension`
- `Elavora\Api\Extension\LogMongoDb\MongoLogWriter`
- `Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter`

## Dependencias de runtime

- `ext-mongodb` `*`
- `elavora/api-framework` `^0.3.1`

## Validacao do pacote

Como rodar os testes, o lint e o PHPStan, e como reproduzir o CI, esta em
[Desenvolvimento e testes](DESENVOLVIMENTO.md). A extensao `mongodb` nao existe na imagem de desenvolvimento: veja
[Extensao mongodb](DESENVOLVIMENTO.md#extensão-mongodb). Para validar o pacote dentro de uma aplicacao consumidora,
rode os testes dela depois de instalar o pacote.

O `composer.json` ganha `require-dev` (`elavora/code-standards`, `phpstan/phpstan`), `scripts` (`lint`, `analyse`,
`check`) e `config` de qualidade: veja [`composer.json` em Automacao](AUTOMACAO.md#composerjson). A secao `require`
(`ext-mongodb`, `elavora/api-framework`) nao muda.

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.