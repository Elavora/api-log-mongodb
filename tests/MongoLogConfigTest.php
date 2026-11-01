<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/USO.md#mongologconfig
 * @see docs/USO.md#configuração
 */
final class MongoLogConfigTest extends TestCase
{
    private const MSG_DATABASE = 'O banco MongoDB de logs e obrigatorio.';
    private const MSG_HOST = 'O host MongoDB de logs e obrigatorio quando a URI nao e informada.';

    public function testBuildsLegacyCompatibleUriAndNamespace(): void
    {
        $config = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'port' => 27018,
            'database' => 'api_logs',
            'collection' => 'application_logs',
            'username' => 'api',
            'password' => 'secret value',
        ]);

        self::assertSame('mongodb://api:secret%20value@mongo:27018', $config->uri());
        self::assertSame('api_logs.application_logs', $config->collectionNamespace());
    }

    public function testUsesUriAndDefaultCollection(): void
    {
        $config = MongoLogConfig::fromArray([
            'uri' => 'mongodb://mongo:27017',
            'database' => 'api_logs',
        ]);

        self::assertSame('mongodb://mongo:27017', $config->uri());
        self::assertSame('logs', $config->collection());
    }

    public function testUsesLegacyDefaultPortWhenBuildingUriFromHost(): void
    {
        $config = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'database' => 'api_logs',
        ]);

        self::assertSame('mongodb://mongo:27017', $config->uri());
    }

    public function testRequiresDatabaseName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017']);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('uriTableProvider')]
    public function testBuildsUriAccordingToDocumentedTable(array $input, string $expectedUri): void
    {
        $config = MongoLogConfig::fromArray($input + ['database' => 'api_logs']);

        self::assertSame($expectedUri, $config->uri());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function uriTableProvider(): array
    {
        return [
            'so host' => [['host' => 'mongo'], 'mongodb://mongo:27017'],
            'host, porta, usuario e senha' => [
                ['host' => 'mongo', 'port' => 27018, 'username' => 'api', 'password' => 'secret value'],
                'mongodb://api:secret%20value@mongo:27018',
            ],
            'porta como texto e so usuario' => [
                ['host' => 'mongo', 'port' => '27018', 'username' => 'api'],
                'mongodb://api:@mongo:27018',
            ],
            'so senha' => [
                ['host' => 'mongo', 'password' => 's3'],
                'mongodb://:s3@mongo:27017',
            ],
            'porta zero vira padrao' => [
                ['host' => 'mongo', 'port' => 0],
                'mongodb://mongo:27017',
            ],
            'porta negativa vira padrao' => [
                ['host' => 'mongo', 'port' => -5],
                'mongodb://mongo:27017',
            ],
            'porta nula vira padrao' => [
                ['host' => 'mongo', 'port' => null],
                'mongodb://mongo:27017',
            ],
            'porta texto vazio vira padrao' => [
                ['host' => 'mongo', 'port' => ''],
                'mongodb://mongo:27017',
            ],
            'porta texto recebe trim' => [
                ['host' => 'mongo', 'port' => ' 27018 '],
                'mongodb://mongo:27018',
            ],
            'porta texto nao e validada como numero' => [
                ['host' => 'mongo', 'port' => 'abc'],
                'mongodb://mongo:abc',
            ],
            'uri ignora host, porta e credenciais' => [
                ['uri' => 'mongodb://x:1', 'host' => 'outro', 'port' => 9, 'username' => 'u', 'password' => 'p'],
                'mongodb://x:1',
            ],
            'uri recebe trim' => [
                ['uri' => '  mongodb://x:1  '],
                'mongodb://x:1',
            ],
            'host recebe trim' => [
                ['host' => '  mongo  '],
                'mongodb://mongo:27017',
            ],
            'usuario so com espacos conta como ausente' => [
                ['host' => 'mongo', 'username' => '   '],
                'mongodb://mongo:27017',
            ],
            'usuario e senha recebem trim e rawurlencode' => [
                ['host' => 'mongo', 'username' => ' a@b ', 'password' => ' p:w/d '],
                'mongodb://a%40b:p%3Aw%2Fd@mongo:27017',
            ],
            'usuario que nao e texto conta como ausente' => [
                ['host' => 'mongo', 'username' => 123],
                'mongodb://mongo:27017',
            ],
            'uri que nao e texto cai para o host' => [
                ['uri' => 123, 'host' => 'mongo'],
                'mongodb://mongo:27017',
            ],
            'uri so com espacos cai para o host' => [
                ['uri' => '   ', 'host' => 'mongo'],
                'mongodb://mongo:27017',
            ],
        ];
    }

    public function testExposesDatabaseCollectionAndNamespace(): void
    {
        $config = MongoLogConfig::fromArray([
            'uri' => 'mongodb://mongo:27017',
            'database' => 'api_logs',
            'collection' => 'application_logs',
        ]);

        self::assertSame('api_logs', $config->database());
        self::assertSame('application_logs', $config->collection());
        self::assertSame('api_logs.application_logs', $config->collectionNamespace());
    }

    public function testDefaultCollectionNamespaceUsesLogs(): void
    {
        $config = MongoLogConfig::fromArray(['host' => 'mongo', 'database' => 'api_logs']);

        self::assertSame('api_logs.logs', $config->collectionNamespace());
    }

    public function testTextOptionsAreTrimmed(): void
    {
        $config = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'database' => '  api_logs  ',
            'collection' => '  application_logs  ',
        ]);

        self::assertSame('api_logs', $config->database());
        self::assertSame('application_logs', $config->collection());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function absentCollectionProvider(): array
    {
        return [
            'vazia' => [''],
            'so espacos' => ['   '],
            'inteiro' => [123],
            'nula' => [null],
            'lista' => [['x']],
        ];
    }

    #[DataProvider('absentCollectionProvider')]
    public function testInvalidCollectionCountsAsAbsentAndUsesDefault(mixed $collection): void
    {
        $config = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'database' => 'api_logs',
            'collection' => $collection,
        ]);

        self::assertSame('logs', $config->collection());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function absentDatabaseProvider(): array
    {
        return [
            'ausente' => [null],
            'vazio' => [''],
            'so espacos' => ['   '],
            'inteiro' => [123],
            'lista' => [['api_logs']],
            'booleano' => [true],
        ];
    }

    #[DataProvider('absentDatabaseProvider')]
    public function testInvalidDatabaseThrowsDocumentedMessage(mixed $database): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::MSG_DATABASE);

        MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017', 'database' => $database]);
    }

    public function testMissingDatabaseKeyThrowsDocumentedMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::MSG_DATABASE);

        MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017']);
    }

    public function testMissingHostWithoutUriThrowsDocumentedMessage(): void
    {
        try {
            MongoLogConfig::fromArray(['database' => 'api_logs']);
            self::fail('Era esperada InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(self::MSG_HOST, $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidHostProvider(): array
    {
        return [
            'host vazio' => [['host' => '']],
            'host so com espacos' => [['host' => '   ']],
            'host que nao e texto' => [['host' => 123]],
            'host nulo' => [['host' => null]],
            'uri vazia sem host' => [['uri' => '  ']],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidHostProvider')]
    public function testInvalidHostWithoutUriThrowsDocumentedMessage(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::MSG_HOST);

        MongoLogConfig::fromArray($input + ['database' => 'api_logs']);
    }

    public function testDatabaseIsCheckedBeforeHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(self::MSG_DATABASE);

        MongoLogConfig::fromArray([]);
    }

    public function testHostIsNotRequiredWhenUriIsPresent(): void
    {
        $config = MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs']);

        self::assertSame('mongodb://mongo:27017', $config->uri());
    }

    public function testUnknownKeysAreIgnored(): void
    {
        $config = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'database' => 'api_logs',
            'desconhecida' => 'x',
            0 => 'y',
        ]);

        self::assertSame('mongodb://mongo:27017', $config->uri());
        self::assertSame('api_logs.logs', $config->collectionNamespace());
    }

    public function testConstructorIsPrivateAndClassIsFinal(): void
    {
        $class = new ReflectionClass(MongoLogConfig::class);

        self::assertTrue($class->isFinal());
        self::assertTrue($class->getConstructor()?->isPrivate());
    }

    public function testTwoCallsWithSameInputYieldEqualConfigs(): void
    {
        $input = ['host' => 'mongo', 'database' => 'api_logs', 'port' => 27018];

        $first = MongoLogConfig::fromArray($input);
        $second = MongoLogConfig::fromArray($input);

        self::assertSame($first->uri(), $second->uri());
        self::assertSame($first->collectionNamespace(), $second->collectionNamespace());
    }
}
