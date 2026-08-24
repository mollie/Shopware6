<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Logger\OrderFileHandler;
use Mollie\Shopware\Component\Logger\OrderLogStorage;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderFileHandler::class)]
final class OrderFileHandlerTest extends TestCase
{
    private string $logDir;

    private OrderFileHandler $handler;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/mollie-order-handler-test-' . uniqid('', true);

        $this->handler = new OrderFileHandler(
            new OrderLogStorage($this->logDir),
            new FakeSettingsService(),
            $this->createStub(Connection::class)
        );
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

    /**
     * Other plugins use "orderNumber" in their log context as well. Those records must
     * not be written into our order log files.
     */
    public function testHandleIgnoresRecordsOfOtherChannels(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, 'foreign', ['orderNumber' => '10000']);

        $result = $this->handler->handle($record);

        $this->assertFalse($result);
        $this->assertFileDoesNotExist($this->logDir . '/mollie/order-10000.log');
    }

    public function testHandleIgnoresRecordsWithoutOrderNumber(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'mollie', Level::Info, 'no order');

        $this->assertFalse($this->handler->handle($record));
    }

    public function testHandleWritesRecordOfMollieChannel(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'mollie', Level::Info, 'ours', ['orderNumber' => '10000']);

        $result = $this->handler->handle($record);

        $this->assertTrue($result);
        $this->assertStringContainsString('ours', (string) file_get_contents($this->logDir . '/mollie/order-10000.log'));
    }
}
