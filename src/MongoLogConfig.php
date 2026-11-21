<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\LogMongoDb;

use InvalidArgumentException;

final class MongoLogConfig
{
    public const DEFAULT_TIMEOUT_MS = 1000;

    private function __construct(
        private readonly string $uri,
        private readonly string $database,
        private readonly string $collection,
        private readonly int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        private readonly bool $failSilently = true,
    ) {}

    /**
     * Cria a configuracao a partir de array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $database = self::optionalString($config['database'] ?? null);
        if ($database === null) {
            throw new InvalidArgumentException('O banco MongoDB de logs e obrigatorio.');
        }

        $collection = self::optionalString($config['collection'] ?? null) ?? 'logs';

        return new self(
            uri: self::buildUri($config),
            database: $database,
            collection: $collection,
            timeoutMs: self::timeout($config),
            failSilently: self::parseFailSilently($config),
        );
    }

    /**
     * Retorna a URI de conexao MongoDB.
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Retorna o banco onde os logs serao gravados.
     */
    public function database(): string
    {
        return $this->database;
    }

    /**
     * Retorna a collection onde os logs serao gravados.
     */
    public function collection(): string
    {
        return $this->collection;
    }

    /**
     * Retorna o timeout, em milissegundos, de selecao de servidor e de conexao.
     */
    public function timeoutMs(): int
    {
        return $this->timeoutMs;
    }

    /**
     * Informa se falhas do MongoDB na gravacao sao engolidas (padrao) em vez de propagadas.
     */
    public function failSilently(): bool
    {
        return $this->failSilently;
    }

    /**
     * Retorna as opcoes de URI para o driver: os timeouts curtos, exceto os que a propria URI ja define.
     *
     * @return array<string, int>
     */
    public function driverOptions(): array
    {
        $defined = [];
        $query = parse_url($this->uri, PHP_URL_QUERY);
        if (is_string($query)) {
            foreach (explode('&', $query) as $pair) {
                $name = strtolower(rawurldecode(explode('=', $pair, 2)[0]));
                if ($name !== '') {
                    $defined[$name] = true;
                }
            }
        }

        $options = [
            'serverSelectionTimeoutMS' => $this->timeoutMs,
            'connectTimeoutMS' => $this->timeoutMs,
            'socketTimeoutMS' => $this->timeoutMs * 2,
        ];

        return array_filter(
            $options,
            static fn(string $name): bool => !isset($defined[strtolower($name)]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Retorna namespace MongoDB no formato database.collection.
     */
    public function collectionNamespace(): string
    {
        return "{$this->database}.{$this->collection}";
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function timeout(array $config): int
    {
        $value = self::option($config, 'timeout_ms', 'MONGO_LOG_TIMEOUT_MS');
        if (is_string($value)) {
            $value = trim($value);
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $timeout = (int) $value;
            if ($timeout > 0) {
                return $timeout;
            }
        }

        return self::DEFAULT_TIMEOUT_MS;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function parseFailSilently(array $config): bool
    {
        $value = self::option($config, 'fail_silently', 'MONGO_LOG_FAIL_SILENTLY');
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value) || is_int($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return true;
    }

    /**
     * Le a opcao do array; se a chave nao existir, cai para a variavel de ambiente.
     *
     * @param array<string, mixed> $config
     */
    private static function option(array $config, string $key, string $env): mixed
    {
        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        $value = $_ENV[$env] ?? $_SERVER[$env] ?? getenv($env);

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildUri(array $config): string
    {
        $uri = self::optionalString($config['uri'] ?? null);
        if ($uri !== null) {
            return $uri;
        }

        $host = self::optionalString($config['host'] ?? null);
        if ($host === null) {
            throw new InvalidArgumentException('O host MongoDB de logs e obrigatorio quando a URI nao e informada.');
        }

        $port = self::optionalPort($config['port'] ?? null);
        $username = self::optionalString($config['username'] ?? null);
        $password = self::optionalString($config['password'] ?? null);

        return 'mongodb://' . self::auth($username, $password) . $host . ':' . $port;
    }

    private static function auth(?string $username, ?string $password): string
    {
        if ($username === null && $password === null) {
            return '';
        }

        return rawurlencode($username ?? '') . ':' . rawurlencode($password ?? '') . '@';
    }

    private static function optionalPort(mixed $value): string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        return self::optionalString($value) ?? '27017';
    }

    private static function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
