<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\LogMongoDb;

use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\LogWriter as FrameworkLogWriter;
use Elavora\Api\Framework\Logging\Logger;
use Closure;
use LogicException;

final class MongoLogExtension implements Extension
{
    private readonly MongoLogConfig $config;

    /** @var Closure(MongoLogConfig): LogWriter|null */
    private readonly ?Closure $writerFactory;

    /**
     * @param array<string, mixed> $config Configuracao MongoDB.
     * @param callable|null $writerFactory Factory opcional para testes ou integracao customizada.
     */
    public function __construct(array $config, ?callable $writerFactory = null)
    {
        $this->config = MongoLogConfig::fromArray($config);
        $this->writerFactory = $writerFactory === null
            ? null
            : Closure::fromCallable($writerFactory);
    }

    /**
     * Registra o writer MongoDB e o logger estruturado.
     */
    public function register(Application $application): void
    {
        $application->container()->bind(
            LogWriter::class,
            fn (): LogWriter => $this->createWriter()
        );
        $application->container()->bind(
            FrameworkLogWriter::class,
            fn (): FrameworkLogWriter => $this->writer($application)
        );
        $application->container()->bind(
            Logger::class,
            fn (): Logger => new Logger($this->writer($application))
        );
    }

    private function writer(Application $application): LogWriter
    {
        $writer = $application->container()->get(LogWriter::class);
        if (!$writer instanceof LogWriter) {
            throw new LogicException('O container retornou um writer MongoDB invalido.');
        }

        return $writer;
    }

    private function createWriter(): LogWriter
    {
        if ($this->writerFactory !== null) {
            return ($this->writerFactory)($this->config);
        }

        return MongoLogWriter::connect($this->config);
    }
}
