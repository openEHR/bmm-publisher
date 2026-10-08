<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger that keeps every record, so tests can assert on what was logged.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public function recordsAt(string $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(array $record): bool => $record['level'] === $level,
        ));
    }
}
