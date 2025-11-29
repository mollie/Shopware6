<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Mollie\Shopware\Component\Logger\RecordAnonymizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Mollie\Shopware\Component\Logger\RecordAnonymizer
 */
#[CoversClass(RecordAnonymizer::class)]
final class RecordAnonymizerTest extends TestCase
{
    public function testNothingIsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();

        $record = [
            'message' => 'nothing has changed'
        ];

        $result = $anonymizer($record);

        $this->assertSame($record, $result);
    }

    public function testIpIsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();

        $record = [
            'message' => 'nothing has changed',
            'extra' => [
                'ip' => '127.0.0.1'
            ]
        ];

        $result = $anonymizer($record);
        $expected = [
            'message' => 'nothing has changed',
            'extra' => [
                'ip' => '127.0.0.0'
            ]
        ];
        $this->assertSame($expected, $result);
    }

    public function testIpV6IsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();

        $record = [
            'message' => 'nothing has changed',
            'extra' => [
                'ip' => '3c3d:d7f6:25ef:29fd:f5b7:1fb1:4241:5208'
            ]
        ];

        $result = $anonymizer($record);
        $expected = [
            'message' => 'nothing has changed',
            'extra' => [
                'ip' => '3c3d:d7f6:25ef:29fd::'
            ]
        ];
        $this->assertSame($expected, $result);
    }

    public function testUrlParametersAreAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();

        $record = [
            'message' => 'nothing has changed',
            'extra' => [
                'ip' => '3c3d:d7f6:25ef:29fd:f5b7:1fb1:4241:5208',
                'url' => 'https://shop.phpunit.mollie/payment/finalize-transaction?token=abc'
            ]
        ];

        $result = $anonymizer($record);
        $expected = [
            'message' => 'nothing has changed',
            'extra' => [
                'ip' => '3c3d:d7f6:25ef:29fd::',
                'url' => 'https://shop.phpunit.mollie/payment/finalize-transaction'
            ]
        ];
        $this->assertSame($expected, $result);
    }
}
