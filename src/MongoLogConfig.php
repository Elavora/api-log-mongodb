<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\LogMongoDb;

use InvalidArgumentException;

final class MongoLogConfig
{
    private function __construct(
        private readonly string $uri,
        private readonly string $database,
        private readonly string $collection
    ) {
    }

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
            collection: $collection
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
     * Retorna namespace MongoDB no formato database.collection.
     */
    public function collectionNamespace(): string
    {
        return "{$this->database}.{$this->collection}";
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
        $username = self::optionalCredential($config, 'username');
        $password = self::optionalCredential($config, 'password');

        return 'mongodb://' . self::auth($username, $password) . $host . ':' . $port;
    }

    private static function auth(?string $username, ?string $password): string
    {
        if (($username === null) !== ($password === null)) {
            throw new InvalidArgumentException(
                'Username e password do MongoDB devem ser informados juntos.'
            );
        }

        if ($username === null || $password === null) {
            return '';
        }

        return rawurlencode($username) . ':' . rawurlencode($password) . '@';
    }

    private static function optionalPort(mixed $value): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return '27017';
        }

        if (is_int($value)) {
            $port = $value;
        } elseif (is_string($value) && preg_match('/^[0-9]+$/', trim($value)) === 1) {
            $port = (int) trim($value);
        } else {
            throw new InvalidArgumentException('A porta MongoDB deve ser um inteiro entre 1 e 65535.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('A porta MongoDB deve estar entre 1 e 65535.');
        }

        return (string) $port;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function optionalCredential(array $config, string $key): ?string
    {
        if (!array_key_exists($key, $config) || $config[$key] === null) {
            return null;
        }

        if (!is_string($config[$key])) {
            throw new InvalidArgumentException("A credencial MongoDB {$key} deve ser uma string.");
        }

        return self::optionalString($config[$key]);
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
