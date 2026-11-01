<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogWriter;
use Elavora\Api\Framework\Contracts\LogWriter as FrameworkLogWriter;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/USO.md#mongologwriter
 * @see docs/USO.md#contractslogwriter
 */
final class MongoLogWriterTest extends TestCase
{
    public function testWritesDocumentWithoutChangingLegacyLogShape(): void
    {
        $namespace = null;
        $persistedEntry = null;
        $entry = [
            'timestamp' => '2026-05-27T12:00:00+00:00',
            'level' => 'error',
            'message' => 'Mongo log',
            'request_id' => '123e4567-e89b-12d3-a456-426614174000',
            'context' => ['foo' => 'bar'],
        ];
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray([
                'uri' => 'mongodb://mongo:27017',
                'database' => 'api_logs',
                'collection' => 'application_logs',
            ]),
            static function (string $target, array $document) use (&$namespace, &$persistedEntry): void {
                $namespace = $target;
                $persistedEntry = $document;
            },
        );

        $writer->write($entry);

        self::assertSame('api_logs.application_logs', $namespace);
        self::assertSame($entry, $persistedEntry);
    }

    public function testUsesDefaultCollectionInNamespace(): void
    {
        $namespaces = [];
        $writer = $this->writerCollecting($namespaces, $documents);

        $writer->write(['message' => 'x']);

        self::assertSame(['api_logs.logs'], $namespaces);
    }

    public function testDoesNotAddRemoveOrRenameFields(): void
    {
        $namespaces = [];
        $entry = [
            'timestamp' => '2026-05-27T12:00:00+00:00',
            'level' => 'info',
            'message' => 'm',
            'request_id' => 'r',
            'context' => [],
            'extra' => ['a' => ['b' => null]],
            'outro' => 1.5,
        ];
        $writer = $this->writerCollecting($namespaces, $documents);

        $writer->write($entry);

        self::assertSame([$entry], $documents);
        self::assertSame(array_keys($entry), array_keys($documents[0]));
    }

    public function testAcceptsArbitraryEntryWithoutValidatingFields(): void
    {
        $namespaces = [];
        $writer = $this->writerCollecting($namespaces, $documents);

        $writer->write([]);
        $writer->write(['qualquer' => 'coisa']);

        self::assertSame([[], ['qualquer' => 'coisa']], $documents);
    }

    public function testCallsInsertOncePerWriteInOrder(): void
    {
        $namespaces = [];
        $writer = $this->writerCollecting($namespaces, $documents);

        $writer->write(['n' => 1]);
        $writer->write(['n' => 2]);
        $writer->write(['n' => 3]);

        self::assertSame([['n' => 1], ['n' => 2], ['n' => 3]], $documents);
        self::assertCount(3, $namespaces);
    }

    public function testDoesNotCallInsertOnConstruction(): void
    {
        $calls = 0;

        new MongoLogWriter(
            MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs']),
            static function () use (&$calls): void {
                $calls++;
            },
        );

        self::assertSame(0, $calls);
    }

    public function testImplementsLocalAndFrameworkLogWriterContracts(): void
    {
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs']),
            static function (): void {},
        );

        self::assertInstanceOf(LogWriter::class, $writer);
        self::assertInstanceOf(FrameworkLogWriter::class, $writer);
    }

    public function testLocalContractExtendsFrameworkContractWithoutOwnMethods(): void
    {
        $contract = new ReflectionClass(LogWriter::class);

        self::assertTrue($contract->isInterface());
        self::assertTrue($contract->isSubclassOf(FrameworkLogWriter::class));
        foreach ($contract->getMethods() as $method) {
            self::assertSame(FrameworkLogWriter::class, $method->getDeclaringClass()->getName());
        }
        self::assertTrue($contract->hasMethod('write'));
    }

    public function testConnectWithoutMongodbExtensionThrowsDocumentedMessage(): void
    {
        if (extension_loaded('mongodb')) {
            self::markTestSkipped('A extensao mongodb esta carregada; o caminho sem extensao nao pode ser exercitado.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A extensao PHP mongodb nao esta disponivel.');

        MongoLogWriter::connect(MongoLogConfig::fromArray([
            'uri' => 'mongodb://mongo:27017',
            'database' => 'api_logs',
        ]));
    }

    /**
     * @param list<string> $namespaces
     * @param list<array<string, mixed>>|null $documents
     */
    private function writerCollecting(array &$namespaces, ?array &$documents): MongoLogWriter
    {
        $documents = [];

        return new MongoLogWriter(
            MongoLogConfig::fromArray(['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs']),
            static function (string $target, array $document) use (&$namespaces, &$documents): void {
                $namespaces[] = $target;
                $documents[] = $document;
            },
        );
    }
}
