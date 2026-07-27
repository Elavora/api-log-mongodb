<?php

declare(strict_types=1);

use Elavora\Api\Extension\LogMongoDb\MongoLogConfig;
use PHPUnit\Framework\TestCase;

final class MongoLogConfigValidationTest extends TestCase
{
    public function testAcceptsIntegerAndDecimalStringPorts(): void
    {
        $integerPort = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'port' => 27017,
            'database' => 'api_logs',
        ]);
        $stringPort = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'port' => '27018',
            'database' => 'api_logs',
        ]);

        self::assertSame('mongodb://mongo:27017', $integerPort->uri());
        self::assertSame('mongodb://mongo:27018', $stringPort->uri());
    }

    public function testRejectsInvalidPorts(): void
    {
        $invalidPorts = [0, -1, 65536, 'abc', 27017.5, true, false];

        foreach ($invalidPorts as $port) {
            try {
                MongoLogConfig::fromArray([
                    'host' => 'mongo',
                    'port' => $port,
                    'database' => 'api_logs',
                ]);
                self::fail('A porta invalida deveria ser rejeitada: ' . var_export($port, true));
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRequiresCompleteCredentialsAndEncodesReservedCharacters(): void
    {
        $config = MongoLogConfig::fromArray([
            'host' => 'mongo',
            'database' => 'api_logs',
            'username' => 'api/user',
            'password' => 'secret:@ value',
        ]);

        self::assertSame(
            'mongodb://api%2Fuser:secret%3A%40%20value@mongo:27017',
            $config->uri()
        );

        $incompleteCredentials = [
            ['username' => 'api'],
            ['password' => 'secret'],
            ['username' => '', 'password' => 'secret'],
            ['username' => 'api', 'password' => ''],
        ];

        foreach ($incompleteCredentials as $credentials) {
            try {
                MongoLogConfig::fromArray(array_merge(
                    ['host' => 'mongo', 'database' => 'api_logs'],
                    $credentials
                ));
                self::fail('Credenciais incompletas deveriam ser rejeitadas.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testExplicitUriTakesPrecedenceOverSeparateConnectionFields(): void
    {
        $config = MongoLogConfig::fromArray([
            'uri' => 'mongodb://cluster.example:27017',
            'host' => 'ignored',
            'port' => false,
            'username' => 'incomplete',
            'database' => 'api_logs',
        ]);

        self::assertSame('mongodb://cluster.example:27017', $config->uri());
    }
}
