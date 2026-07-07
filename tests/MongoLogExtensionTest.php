<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogExtension;
use Elavora\Api\Framework\Application;
use PHPUnit\Framework\TestCase;

final class MongoLogExtensionTest extends TestCase
{
    public function testRegistersLocalWriterContractWithoutMongoConnection(): void
    {
        $receivedConfig = null;
        $fakeWriter = new class implements LogWriter {
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
            }
        ));

        $writer = $application->container()->get(LogWriter::class);
        $writer->write(['message' => 'teste']);

        self::assertSame($fakeWriter, $writer);
        self::assertSame('api_logs.logs', $receivedConfig?->collectionNamespace());
        self::assertSame([['message' => 'teste']], $fakeWriter->entries);
    }
}
