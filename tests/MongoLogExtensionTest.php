<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Extension\LogMongoDb\MongoLogWriter;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\LogWriter as FrameworkLogWriter;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/USO.md#mongologextension
 */
final class MongoLogExtensionTest extends TestCase
{
    public function testRegistersLocalWriterContractWithoutMongoConnection(): void
    {
        $receivedConfig = null;
        $fakeWriter = new class implements LogWriter {
            /** @var list<array<string, mixed>> */
            public array $entries = [];

            public function write(array $entry): void
            {
                $this->entries[] = $entry;
            }
        };
        $application = Application::create()->extend(new MongoLogExtension(
            [
                'uri' => 'mongodb://mongo:27017',
                'database' => 'api_logs',
            ],
            static function (MongoLogConfig $config) use (&$receivedConfig, $fakeWriter): LogWriter {
                $receivedConfig = $config;

                return $fakeWriter;
            },
        ));

        $writer = $application->container()->get(LogWriter::class);
        $writer->write(['message' => 'teste']);

        self::assertSame($fakeWriter, $writer);
        self::assertSame('api_logs.logs', $receivedConfig?->collectionNamespace());
        self::assertSame([['message' => 'teste']], $fakeWriter->entries);
    }

    public function testImplementsFrameworkExtensionContract(): void
    {
        $extension = new MongoLogExtension(['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs']);

        self::assertInstanceOf(Extension::class, $extension);
    }

    public function testInvalidConfigThrowsInConstructorWithoutDatabase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O banco MongoDB de logs e obrigatorio.');

        new MongoLogExtension(['uri' => 'mongodb://mongo:27017']);
    }

    public function testInvalidConfigThrowsInConstructorWithoutHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O host MongoDB de logs e obrigatorio quando a URI nao e informada.');

        new MongoLogExtension(['database' => 'api_logs']);
    }

    public function testDatabaseErrorWinsOverHostErrorInConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O banco MongoDB de logs e obrigatorio.');

        new MongoLogExtension([]);
    }

    public function testRegisterDoesNotCreateWriterNorConnect(): void
    {
        $calls = 0;
        $fake = $this->silentWriter();

        Application::create()->extend(new MongoLogExtension(
            ['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs'],
            static function (MongoLogConfig $config) use (&$calls, $fake): LogWriter {
                $calls++;

                return $fake;
            },
        ));

        self::assertSame(0, $calls);
    }

    public function testRegisterWithoutFactoryDoesNotRequireMongodbExtensionNorServer(): void
    {
        $application = Application::create()->extend(new MongoLogExtension([
            'host' => 'servidor-que-nao-existe.invalid',
            'database' => 'api_logs',
        ]));

        self::assertInstanceOf(Application::class, $application);
    }

    public function testWriterIsCreatedOnceAndSharedByAllThreeTypes(): void
    {
        $calls = 0;
        $fake = $this->silentWriter();
        $application = Application::create()->extend(new MongoLogExtension(
            ['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs'],
            static function (MongoLogConfig $config) use (&$calls, $fake): LogWriter {
                $calls++;

                return $fake;
            },
        ));
        $container = $application->container();

        $first = $container->get(LogWriter::class);
        $second = $container->get(LogWriter::class);
        $framework = $container->get(FrameworkLogWriter::class);

        self::assertSame($first, $second);
        self::assertSame($first, $framework);
        self::assertSame(1, $calls);
    }

    public function testFactoryReceivesConfigBuiltFromTheGivenArray(): void
    {
        $received = null;
        $fake = $this->silentWriter();
        $application = Application::create()->extend(new MongoLogExtension(
            [
                'host' => 'mongo',
                'port' => 27018,
                'username' => 'api',
                'password' => 's3',
                'database' => 'api_logs',
                'collection' => 'application_logs',
            ],
            static function (MongoLogConfig $config) use (&$received, $fake): LogWriter {
                $received = $config;

                return $fake;
            },
        ));

        $application->container()->get(LogWriter::class);

        self::assertInstanceOf(MongoLogConfig::class, $received);
        self::assertSame('mongodb://api:s3@mongo:27018', $received->uri());
        self::assertSame('api_logs', $received->database());
        self::assertSame('application_logs', $received->collection());
    }

    public function testFactoryWriterWithInsertFunctionStoresInConfiguredNamespace(): void
    {
        $stored = [];
        $application = Application::create()->extend(new MongoLogExtension(
            ['uri' => 'mongodb://mongo:27017', 'database' => 'api_logs'],
            static function (MongoLogConfig $config) use (&$stored): LogWriter {
                return new MongoLogWriter(
                    $config,
                    static function (string $namespace, array $entry) use (&$stored): void {
                        $stored[] = [$namespace, $entry];
                    },
                );
            },
        ));

        $application->container()->get(LogWriter::class)->write(['message' => 'oi']);

        self::assertSame([['api_logs.logs', ['message' => 'oi']]], $stored);
    }

    private function silentWriter(): LogWriter
    {
        return new class implements LogWriter {
            public function write(array $entry): void {}
        };
    }
}
