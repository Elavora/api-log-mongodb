<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Extension\LogMongoDb\MongoLogWriter;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Logging\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/USO.md#mongologextension
 * @see docs/USO.md#documento-gravado
 */
final class MongoLoggerTest extends TestCase
{
    public function testExtensionRegistersFrameworkLogger(): void
    {
        $fakeWriter = $this->collectingWriter();
        $application = Application::create()->extend(new MongoLogExtension(
            [
                'uri' => 'mongodb://mongo:27017',
                'database' => 'api_logs',
            ],
            static fn(MongoLogConfig $config): LogWriter => $fakeWriter,
        ));

        $logger = $application->container()->get(Logger::class);

        self::assertInstanceOf(Logger::class, $logger);
    }

    public function testLoggerIsSharedBetweenResolutions(): void
    {
        $application = $this->applicationWith($this->collectingWriter());

        self::assertSame(
            $application->container()->get(Logger::class),
            $application->container()->get(Logger::class),
        );
    }

    public function testLoggerWritesDocumentedEntryThroughTheWriter(): void
    {
        $writer = $this->collectingWriter();
        $application = $this->applicationWith($writer);

        $application->container()->get(Logger::class)->info('Requisicao recebida', ['route' => '/health']);

        self::assertCount(1, $writer->entries);
        $entry = $writer->entries[0];
        self::assertSame('info', $entry['level']);
        self::assertSame('Requisicao recebida', $entry['message']);
        self::assertSame(['route' => '/health'], $entry['context']);
        self::assertArrayHasKey('request_id', $entry);
        self::assertArrayHasKey('timestamp', $entry);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $entry['timestamp'],
        );
    }

    public function testLoggerLevelsAreDebugInfoWarningAndError(): void
    {
        $writer = $this->collectingWriter();
        $logger = $this->applicationWith($writer)->container()->get(Logger::class);

        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');

        self::assertSame(
            ['debug', 'info', 'warning', 'error'],
            array_column($writer->entries, 'level'),
        );
    }

    public function testExceptionEntryHasErrorLevelAndNoExceptionMessage(): void
    {
        $writer = $this->collectingWriter();
        $logger = $this->applicationWith($writer)->container()->get(Logger::class);

        $logger->exception(new RuntimeException('segredo do usuario', 42), ['route' => '/x']);

        $entry = $writer->entries[0];
        self::assertSame('error', $entry['level']);
        self::assertSame('Unhandled exception', $entry['message']);
        self::assertSame('/x', $entry['context']['route']);
        self::assertSame(RuntimeException::class, $entry['context']['exception']['class']);
        self::assertSame(42, $entry['context']['exception']['code']);
        self::assertStringNotContainsString('segredo do usuario', (string) json_encode($entry));
    }

    public function testLoggerWritesToMongoDocumentNamespace(): void
    {
        $stored = [];
        $application = Application::create()->extend(new MongoLogExtension(
            [
                'uri' => 'mongodb://mongo:27017',
                'database' => 'api_logs',
                'collection' => 'application_logs',
            ],
            static function (MongoLogConfig $config) use (&$stored): LogWriter {
                return new MongoLogWriter(
                    $config,
                    static function (string $namespace, array $entry) use (&$stored): void {
                        $stored[] = [$namespace, $entry];
                    },
                );
            },
        ));

        $application->container()->get(Logger::class)->info('Requisicao recebida', ['route' => '/health']);

        self::assertCount(1, $stored);
        self::assertSame('api_logs.application_logs', $stored[0][0]);
        self::assertSame('Requisicao recebida', $stored[0][1]['message']);
    }

    private function applicationWith(LogWriter $writer): Application
    {
        return Application::create()->extend(new MongoLogExtension(
            [
                'uri' => 'mongodb://mongo:27017',
                'database' => 'api_logs',
            ],
            static fn(MongoLogConfig $config): LogWriter => $writer,
        ));
    }

    private function collectingWriter(): object
    {
        return new class implements LogWriter {
            /** @var list<array<string, mixed>> */
            public array $entries = [];

            public function write(array $entry): void
            {
                $this->entries[] = $entry;
            }
        };
    }
}
