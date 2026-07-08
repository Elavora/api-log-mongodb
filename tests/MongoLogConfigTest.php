<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use PHPUnit\Framework\TestCase;

final class MongoLogConfigTest extends TestCase
{
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
}
