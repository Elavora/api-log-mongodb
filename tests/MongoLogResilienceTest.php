<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogWriter;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/USO.md#resiliência-e-timeouts
 * @see docs/USO.md#retenção-por-ttl
 */
final class MongoLogResilienceTest extends TestCase
{
    private const BASE = ['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs'];

    public function testWriteFailureIsSwallowedAndReportedOnlyOnce(): void
    {
        $reports = [];
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(self::BASE),
            static function (): void {
                throw new RuntimeException('mongodb://user:senha@mongo falhou');
            },
            static function (string $message) use (&$reports): void {
                $reports[] = $message;
            },
        );

        $writer->write(['message' => 'a']);
        $writer->write(['message' => 'b']);

        self::assertCount(1, $reports);
        self::assertStringContainsString('RuntimeException', $reports[0]);
        self::assertStringNotContainsString('senha', $reports[0]);
    }

    public function testAfterFailureNextWritesSkipMongoDuringCooldown(): void
    {
        $calls = 0;
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(self::BASE),
            static function () use (&$calls): void {
                $calls++;
                throw new RuntimeException('x');
            },
            static function (): void {},
        );

        $writer->write(['n' => 1]);
        $writer->write(['n' => 2]);
        $writer->write(['n' => 3]);

        self::assertSame(1, $calls);
    }

    public function testFailureCanBePropagatedWhenFailSilentlyIsFalse(): void
    {
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(self::BASE + ['fail_silently' => false]),
            static function (): void {
                throw new RuntimeException('falhou');
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('falhou');

        $writer->write(['message' => 'a']);
    }

    public function testReporterFailureNeverBreaksTheWrite(): void
    {
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(self::BASE),
            static function (): void {
                throw new Error('falhou');
            },
            static function (): void {
                throw new LogicException('reporter quebrado');
            },
        );

        $writer->write(['message' => 'a']);

        $this->addToAssertionCount(1);
    }

    public function testCreatedAtIsABsonDateMatchingTheTimestamp(): void
    {
        $documents = [];
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(self::BASE),
            static function (string $namespace, array $document) use (&$documents): void {
                $documents[] = $document;
            },
        );

        $writer->write(['timestamp' => '2026-05-27T12:00:00+00:00', 'message' => 'm']);

        self::assertSame('2026-05-27T12:00:00+00:00', $documents[0]['timestamp']);
        self::assertInstanceOf(UTCDateTime::class, $documents[0]['created_at']);
        self::assertSame(
            (new DateTimeImmutable('2026-05-27T12:00:00+00:00'))->getTimestamp() * 1000,
            (int) (string) $documents[0]['created_at'],
        );
    }

    public function testCreatedAtFallsBackToNowAndIsNotOverwritten(): void
    {
        $documents = [];
        $writer = new MongoLogWriter(
            MongoLogConfig::fromArray(self::BASE),
            static function (string $namespace, array $document) use (&$documents): void {
                $documents[] = $document;
            },
        );
        $own = new UTCDateTime(1000);

        $writer->write(['timestamp' => 'nao e data']);
        $writer->write(['created_at' => $own]);

        self::assertInstanceOf(UTCDateTime::class, $documents[0]['created_at']);
        self::assertSame($own, $documents[1]['created_at']);
    }

    public function testDefaultsOfTimeoutAndFailSilently(): void
    {
        $config = MongoLogConfig::fromArray(self::BASE);

        self::assertSame(1000, $config->timeoutMs());
        self::assertTrue($config->failSilently());
        self::assertSame(
            ['serverSelectionTimeoutMS' => 1000, 'connectTimeoutMS' => 1000, 'socketTimeoutMS' => 2000],
            $config->driverOptions(),
        );
    }

    public function testTimeoutAndFailSilentlyFromArray(): void
    {
        $config = MongoLogConfig::fromArray(self::BASE + ['timeout_ms' => '500', 'fail_silently' => 'false']);

        self::assertSame(500, $config->timeoutMs());
        self::assertFalse($config->failSilently());
        self::assertSame(500, $config->driverOptions()['connectTimeoutMS']);
    }

    public function testInvalidTimeoutAndFailSilentlyFallBackToDefaults(): void
    {
        $config = MongoLogConfig::fromArray(self::BASE + ['timeout_ms' => '-5', 'fail_silently' => 'talvez']);

        self::assertSame(1000, $config->timeoutMs());
        self::assertTrue($config->failSilently());
        self::assertSame(1000, MongoLogConfig::fromArray(self::BASE + ['timeout_ms' => 0])->timeoutMs());
    }

    public function testReadsEnvironmentWhenKeysAreMissing(): void
    {
        putenv('MONGO_LOG_TIMEOUT_MS=250');
        putenv('MONGO_LOG_FAIL_SILENTLY=false');

        try {
            $config = MongoLogConfig::fromArray(self::BASE);
            $explicit = MongoLogConfig::fromArray(self::BASE + ['timeout_ms' => 900, 'fail_silently' => true]);
        } finally {
            putenv('MONGO_LOG_TIMEOUT_MS');
            putenv('MONGO_LOG_FAIL_SILENTLY');
        }

        self::assertSame(250, $config->timeoutMs());
        self::assertFalse($config->failSilently());
        self::assertSame(900, $explicit->timeoutMs());
        self::assertTrue($explicit->failSilently());
    }

    public function testDoesNotOverrideTimeoutsAlreadyDefinedInTheUri(): void
    {
        $uri = 'mongodb://mongo:27017/?serverSelectionTimeoutMS=5000&replicaSet=rs0&SOCKETTIMEOUTMS=7000';
        $config = MongoLogConfig::fromArray(['uri' => $uri, 'database' => 'api_logs', 'timeout_ms' => 300]);

        self::assertSame(['connectTimeoutMS' => 300], $config->driverOptions());
        self::assertSame($uri, $config->uri());
    }

    public function testUnreachableServerWithRealDriverIsFastAndSilent(): void
    {
        if (!extension_loaded('mongodb')) {
            self::markTestSkipped('Requer a extensao mongodb.');
        }

        $reports = [];
        $config = MongoLogConfig::fromArray([
            'uri' => 'mongodb://127.0.0.1:1',
            'database' => 'api_logs',
            'timeout_ms' => 200,
        ]);
        $writer = MongoLogWriter::connect($config);
        $start = microtime(true);

        $writer->write(['message' => 'a']);

        self::assertLessThan(3.0, microtime(true) - $start);
        self::assertSame([], $reports);
    }
}
