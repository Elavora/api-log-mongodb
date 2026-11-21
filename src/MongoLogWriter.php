<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\LogMongoDb;

use Elavora\Api\Extension\LogMongoDb\Contracts\LogWriter;
use Closure;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\WriteConcern;
use RuntimeException;
use Throwable;

final class MongoLogWriter implements LogWriter
{
    /** Segundos sem tentar o MongoDB depois de uma falha, para nao somar timeouts na mesma requisicao. */
    private const COOLDOWN_SECONDS = 5.0;

    /** @var Closure(string, array<string, mixed>): void */
    private readonly Closure $insertDocument;

    /** @var Closure(string): void */
    private readonly Closure $reportError;

    private ?float $failedAt = null;

    private bool $reported = false;

    /**
     * @param MongoLogConfig $config Configuracao do destino MongoDB.
     * @param callable $insertDocument Funcao que insere o documento.
     * @param callable|null $reportError Recebe a mensagem de falha; o padrao e o error_log do PHP.
     */
    public function __construct(
        private readonly MongoLogConfig $config,
        callable $insertDocument,
        ?callable $reportError = null,
    ) {
        $this->insertDocument = Closure::fromCallable($insertDocument);
        $this->reportError = $reportError === null
            ? static function (string $message): void {
                error_log($message);
            }
            : Closure::fromCallable($reportError);
    }

    /**
     * Cria um writer conectado ao MongoDB. A conexao e preguicosa: o Manager so e criado na primeira gravacao.
     */
    public static function connect(MongoLogConfig $config): self
    {
        if (!class_exists(Manager::class)) {
            throw new RuntimeException('A extensao PHP mongodb nao esta disponivel.');
        }

        $manager = null;

        return new self(
            config: $config,
            insertDocument: static function (string $namespace, array $entry) use ($config, &$manager): void {
                $manager ??= new Manager($config->uri(), $config->driverOptions());

                $bulk = new BulkWrite();
                $bulk->insert($entry);

                $manager->executeBulkWrite(
                    $namespace,
                    $bulk,
                    [
                        'writeConcern' => new WriteConcern(WriteConcern::MAJORITY, $config->timeoutMs()),
                    ],
                );
            },
        );
    }

    /**
     * Grava uma entrada de log no MongoDB. Falhas do MongoDB nao propagam, a menos que fail_silently seja false.
     *
     * @param array<string, mixed> $entry
     */
    public function write(array $entry): void
    {
        if ($this->failedAt !== null && (microtime(true) - $this->failedAt) < self::COOLDOWN_SECONDS) {
            return;
        }

        try {
            ($this->insertDocument)($this->config->collectionNamespace(), $this->withCreatedAt($entry));
            $this->failedAt = null;
        } catch (Throwable $exception) {
            if (!$this->config->failSilently()) {
                throw $exception;
            }

            $this->failedAt = microtime(true);
            $this->report($exception);
        }
    }

    /**
     * Acrescenta created_at (BSON Date) para permitir indice TTL; o timestamp em texto continua como esta.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function withCreatedAt(array $entry): array
    {
        if (array_key_exists('created_at', $entry) || !class_exists(UTCDateTime::class)) {
            return $entry;
        }

        $moment = new DateTimeImmutable();
        $timestamp = $entry['timestamp'] ?? null;
        if (is_string($timestamp)) {
            try {
                $moment = new DateTimeImmutable($timestamp);
            } catch (Throwable) {
                // timestamp ilegivel: usa o instante atual.
            }
        }

        $entry['created_at'] = new UTCDateTime($moment);

        return $entry;
    }

    private function report(Throwable $exception): void
    {
        if ($this->reported) {
            return;
        }
        $this->reported = true;

        // So a classe da excecao: a mensagem do driver pode conter a URI de conexao com credenciais.
        try {
            ($this->reportError)(
                'api-log-mongodb: falha ao gravar o log no MongoDB (' . $exception::class . '); '
                . 'o log foi descartado e a requisicao segue.',
            );
        } catch (Throwable) {
            // o relato nunca pode quebrar a requisicao.
        }
    }
}
