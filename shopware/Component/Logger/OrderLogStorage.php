<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Single access point for the per-order log files. Owns the storage location
 * and the `order-{orderNumber}.log` naming convention so that the writer
 * (OrderFileHandler) and the cleanup (CleanUpLoggerScheduledTaskHandler) can
 * never drift apart. If the log storage ever moves away from the local
 * filesystem, this is the only class that has to change.
 */
final class OrderLogStorage
{
    private const SUB_DIR = 'mollie';
    private const PREFIX = 'order-';
    private const SUFFIX = '.log';

    public function __construct(
        #[Autowire(value: '%kernel.logs_dir%')]
        private string $logDir
    ) {
    }

    /**
     * Ensures the storage directory exists and returns the absolute path the
     * writer should stream the log record to.
     */
    public function resolveLogFile(string $orderNumber): string
    {
        $directory = $this->directory();
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $this->path($orderNumber);
    }

    /**
     * Returns up to $limit order numbers that currently have a log file.
     * readdir() has no persistent cursor, so on very large directories files
     * are distributed fairly across many runs.
     *
     * @return list<string>
     */
    public function listOrderNumbers(int $limit): array
    {
        $directory = $this->directory();
        if (! is_dir($directory)) {
            return [];
        }

        $handle = opendir($directory);
        if ($handle === false) {
            return [];
        }

        $orderNumbers = [];
        while (($file = readdir($handle)) !== false) {
            if (count($orderNumbers) >= $limit) {
                break;
            }

            $orderNumber = $this->extractOrderNumber($file);
            if ($orderNumber === null) {
                continue;
            }

            if (! is_file($directory . '/' . $file)) {
                continue;
            }

            $orderNumbers[] = $orderNumber;
        }

        closedir($handle);

        return $orderNumbers;
    }

    public function getModifiedTime(string $orderNumber): ?int
    {
        $file = $this->path($orderNumber);
        if (! is_file($file)) {
            return null;
        }

        $modifiedTime = filemtime($file);

        return $modifiedTime === false ? null : $modifiedTime;
    }

    public function delete(string $orderNumber): void
    {
        $file = $this->path($orderNumber);
        if (! is_file($file)) {
            return;
        }

        if (! unlink($file)) {
            throw new \RuntimeException('Could not delete order log file: ' . basename($file));
        }
    }

    private function path(string $orderNumber): string
    {
        return $this->directory() . '/' . self::PREFIX . $orderNumber . self::SUFFIX;
    }

    private function directory(): string
    {
        return $this->logDir . '/' . self::SUB_DIR;
    }

    private function extractOrderNumber(string $file): ?string
    {
        if (! str_starts_with($file, self::PREFIX) || ! str_ends_with($file, self::SUFFIX)) {
            return null;
        }

        $orderNumber = substr($file, strlen(self::PREFIX), -strlen(self::SUFFIX));

        return $orderNumber === '' ? null : $orderNumber;
    }
}
