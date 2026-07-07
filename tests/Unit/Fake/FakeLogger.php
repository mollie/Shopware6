<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Psr\Log\AbstractLogger;

final class FakeLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: mixed, message: string, context: array<mixed>}>
     */
    private array $records = [];

    /**
     * @param array<mixed> $context
     * @param mixed $level
     * @param mixed $message
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return array<int, array{level: mixed, message: string, context: array<mixed>}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function hasRecordThatContains(string $level, string $needle): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && str_contains($record['message'], $needle)) {
                return true;
            }
        }

        return false;
    }
}
