<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Mollie\Shopware\Component\Logger\RecordAnonymizer;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordAnonymizer::class)]
final class RecordAnonymizerTest extends TestCase
{
    public function testNothingIsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();

        $record = new LogRecord(new \DateTimeImmutable(), 'test', Level::Info, 'nothing has changed');
        $result = $anonymizer($record);

        $this->assertEquals($record, $result);
    }

    public function testIpIsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();
        $now = new \DateTimeImmutable();

        $record = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [], [
            'ip' => '127.0.0.1',
        ]);
        $result = $anonymizer($record);
        $expected = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [], [
            'ip' => '127.0.0.0'
        ]);

        $this->assertEquals($expected, $result);
    }

    public function testIpV6IsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();
        $now = new \DateTimeImmutable();

        $record = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [], [
            'ip' => '3c3d:d7f6:25ef:29fd:f5b7:1fb1:4241:5208'
        ]);
        $result = $anonymizer($record);

        $expected = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [], [
            'ip' => '3c3d:d7f6:25ef:29fd::'
        ]);
        $this->assertEquals($expected, $result);
    }

    public function testUrlParametersAreAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();
        $now = new \DateTimeImmutable();
        $record = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [], [
            'ip' => '3c3d:d7f6:25ef:29fd:f5b7:1fb1:4241:5208',
            'url' => 'https://shop.phpunit.mollie/payment/finalize-transaction?token=abc'
        ]);

        $result = $anonymizer($record);
        $expected = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [], [
            'ip' => '3c3d:d7f6:25ef:29fd::',
            'url' => 'https://shop.phpunit.mollie/payment/finalize-transaction'
        ]);

        $this->assertEquals($expected, $result);
    }
}
