<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use Elavora\Api\Extension\LogMongoDb\MongoLogWriter;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use PHPUnit\Framework\TestCase;

final class MongoLogIntegrationTest extends TestCase
{
    public function testWritesAndReadsDocumentFromMongoDb(): void
    {
        if (getenv('MONGODB_INTEGRATION') !== '1') {
            self::markTestSkipped('Defina MONGODB_INTEGRATION=1 para executar o teste com MongoDB real.');
        }

        $host = self::environment('MONGODB_HOST', '127.0.0.1');
        $port = self::environment('MONGODB_PORT', '27017');
        $identifier = bin2hex(random_bytes(8));
        $database = 'elavora_log_it_' . $identifier;
        $collection = 'logs_' . $identifier;
        $config = MongoLogConfig::fromArray([
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'collection' => $collection,
        ]);
        $manager = new Manager($config->uri());
        $namespace = $config->collectionNamespace();
        $documentWasWritten = false;
        $entry = [
            'integration_id' => $identifier,
            'timestamp' => '2026-07-26T12:00:00+00:00',
            'level' => 'info',
            'message' => 'Teste de integracao MongoDB',
            'request_id' => 'request-' . $identifier,
            'context' => [
                'suite' => 'integration',
                'isolated' => true,
            ],
        ];

        try {
            $writer = MongoLogWriter::connect($config);
            $writer->write($entry);
            $documentWasWritten = true;

            $documents = $manager->executeQuery(
                $namespace,
                new Query(['integration_id' => $identifier], ['limit' => 1])
            )->toArray();

            self::assertCount(1, $documents);
            $document = $documents[0];
            self::assertInstanceOf(\stdClass::class, $document);
            self::assertSame($identifier, $document->integration_id);
            self::assertSame($entry['timestamp'], $document->timestamp);
            self::assertSame($entry['level'], $document->level);
            self::assertSame($entry['message'], $document->message);
            self::assertSame($entry['request_id'], $document->request_id);
            $context = $document->context;
            self::assertInstanceOf(\stdClass::class, $context);
            self::assertSame('integration', $context->suite);
            self::assertTrue($context->isolated);
        } finally {
            try {
                if ($documentWasWritten) {
                    $cleanup = new BulkWrite();
                    $cleanup->delete(['integration_id' => $identifier], ['limit' => 1]);
                    $result = $manager->executeBulkWrite($namespace, $cleanup);

                    self::assertSame(1, $result->getDeletedCount());
                }
            } finally {
                $manager->executeCommand($database, new Command(['dropDatabase' => 1]));
            }
        }
    }

    private static function environment(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }
}
