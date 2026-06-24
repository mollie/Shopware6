<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Mollie\Shopware\Component\Logger\OrderLogStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderLogStorage::class)]
final class OrderLogStorageTest extends TestCase
{
    private string $logDir;

    private OrderLogStorage $storage;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/mollie-storage-test-' . uniqid('', true);
        $this->storage = new OrderLogStorage($this->logDir);
    }

    protected function tearDown(): void
    {
        $mollieDir = $this->logDir . '/mollie';
        foreach (glob($mollieDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($mollieDir)) {
            rmdir($mollieDir);
        }
        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }
    }

    public function testResolveLogFileCreatesDirectoryAndReturnsPath(): void
    {
        $path = $this->storage->resolveLogFile('10000');

        $this->assertSame($this->logDir . '/mollie/order-10000.log', $path);
        $this->assertDirectoryExists($this->logDir . '/mollie');
    }

    public function testListOrderNumbersReturnsOnlyOrderLogs(): void
    {
        $this->writeFile('order-10000.log');
        $this->writeFile('order-20000.log');
        $this->writeFile('something-else.log');
        $this->writeFile('order-.log');

        $orderNumbers = $this->storage->listOrderNumbers(100);

        sort($orderNumbers);
        $this->assertSame(['10000', '20000'], $orderNumbers);
    }

    public function testListOrderNumbersRespectsLimit(): void
    {
        $this->writeFile('order-10000.log');
        $this->writeFile('order-20000.log');
        $this->writeFile('order-30000.log');

        $this->assertCount(2, $this->storage->listOrderNumbers(2));
    }

    public function testListOrderNumbersReturnsEmptyWhenDirectoryMissing(): void
    {
        $this->assertSame([], $this->storage->listOrderNumbers(100));
    }

    public function testGetModifiedTimeReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->storage->getModifiedTime('99999'));
    }

    public function testGetModifiedTimeReturnsTimestamp(): void
    {
        $this->writeFile('order-10000.log');
        $expected = time() - 1234;
        touch($this->logDir . '/mollie/order-10000.log', $expected);

        $this->assertSame($expected, $this->storage->getModifiedTime('10000'));
    }

    public function testDeleteRemovesFile(): void
    {
        $this->writeFile('order-10000.log');

        $this->storage->delete('10000');

        $this->assertFileDoesNotExist($this->logDir . '/mollie/order-10000.log');
    }

    public function testDeleteIsNoopForMissingFile(): void
    {
        $this->expectNotToPerformAssertions();

        $this->storage->delete('does-not-exist');
    }

    private function writeFile(string $name): void
    {
        $dir = $this->logDir . '/mollie';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $name, 'log content');
    }
}
