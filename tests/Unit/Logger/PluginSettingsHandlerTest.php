<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Logger\PluginSettingsHandler;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(PluginSettingsHandler::class)]
/**
 * @coversDefaultClass \Mollie\Shopware\Component\Logger\PluginSettingsHandler
 */
final class PluginSettingsHandlerTest extends TestCase
{
    public function testHandleIsFalseWithoutConnection(): void
    {
        $fakeSettingsService = new FakeSettingsService();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('isConnected')->willReturn(false);

        $handler = new PluginSettingsHandler($fakeSettingsService, $connection, '');
        $record = [
            'message' => 'test'
        ];
        $result = $handler->handle($record);

        $this->assertFalse($result);
    }

    public function testHandleIsFalseForDifferentChannel(): void
    {
        $fakeSettingsService = new FakeSettingsService();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('isConnected')->willReturn(true);

        $handler = new PluginSettingsHandler($fakeSettingsService, $connection, '');
        $record = [
            'message' => 'test',
            'channel' => 'test'
        ];
        if (class_exists(LogRecord::class)) {
            $record = new LogRecord(new \DateTimeImmutable(),'test',Level::Info,'test');
        }
        $result = $handler->handle($record);

        $this->assertFalse($result);
    }

    public function testHandleIsFalseFowLowerLogLevel(): void
    {
        $loggerSettings = new LoggerSettings(false, 0);
        $fakeSettingsService = new FakeSettingsService($loggerSettings);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('isConnected')->willReturn(true);

        $handler = new PluginSettingsHandler($fakeSettingsService, $connection, '');
        $record = [
            'message' => 'test',
            'channel' => 'mollie',
            'level' => LogLevel::DEBUG,

            'extra' => [],
            'context' => [],
            'datetime' => new \DateTime()
        ];
        if (class_exists(LogRecord::class)) {
            $record = new LogRecord(new \DateTimeImmutable(),'mollie',Level::Debug,'test');
        }
        $result = $handler->handle($record);

        $this->assertFalse($result);
    }

    public function testHandleIsWorking(): void
    {
        $loggerSettings = new LoggerSettings(true, 0);
        $fakeSettingsService = new FakeSettingsService($loggerSettings);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('isConnected')->willReturn(true);

        $handler = new PluginSettingsHandler($fakeSettingsService, $connection, '', false);
        $record = [
            'message' => 'test',
            'channel' => 'mollie',
            'level' => LogLevel::DEBUG,

            'extra' => [],
            'context' => [],
            'datetime' => new \DateTime()
        ];
        if (class_exists(LogRecord::class)) {
            $record = new LogRecord(new \DateTimeImmutable(),'mollie',Level::Debug,'test');
        }
        $result = $handler->handle($record);

        $this->assertTrue($result);
    }
}
