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

    public function testPersonalDataInContextIsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();
        $now = new \DateTimeImmutable();
        $record = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [
            'payload' => [
                'billingAddress' => [
                    'givenName' => 'Maximilian',
                    'familyName' => 'Mollie',
                    'organizationName' => 'Mollie Company',
                    'email' => 'test@mollie.com',
                    'phone' => '+490123456789',
                    'streetAndNumber' => 'Mollie Street 123',
                    'postalCode' => '12345',
                ]
            ]
        ]);

        $result = $anonymizer($record);

        $this->assertEquals('**', $result['context']['payload']['billingAddress']['givenName']);
        $this->assertEquals('**', $result['context']['payload']['billingAddress']['familyName']);
        $this->assertEquals('**', $result['context']['payload']['billingAddress']['organizationName']);
        $this->assertEquals('**', $result['context']['payload']['billingAddress']['email']);
        $this->assertEquals('**', $result['context']['payload']['billingAddress']['phone']);
        $this->assertEquals('**', $result['context']['payload']['billingAddress']['streetAndNumber']);
        $this->assertEquals('**', $result['context']['payload']['billingAddress']['postalCode']);
    }

    public function testTokenParametersInUrlsAreAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();
        $now = new \DateTimeImmutable();
        $record = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [
            'checkoutUrl' => 'https://www.mollie.com/checkout/test-mode?method=alma&token=6.balale',
            'finalizeUrl' => 'https://mollie-local.diwc.de/payment/finalize-transaction?_sw_payment_token=eyeo555n4777nclr771n5zcidj6ym96m3456'
        ]);

        $result = $anonymizer($record);

        $this->assertStringContainsString('token=6.**', $result['context']['checkoutUrl']);
        $this->assertStringNotContainsString('balale', $result['context']['checkoutUrl']);
        $this->assertStringContainsString('_sw_payment_token=ey**', $result['context']['finalizeUrl']);
        $this->assertStringNotContainsString('eyeo555n4777nclr771n5zcidj6ym96m3456', $result['context']['finalizeUrl']);
    }

    public function testApplePayPaymentTokenIsAnonymized(): void
    {
        $anonymizer = new RecordAnonymizer();
        $now = new \DateTimeImmutable();
        $applePayToken = '{"paymentData":{"data":"zazq6d9tsJzah148grEdwNBWosUlEdnmu9c/tpEidah","signature":"MIAGCSqGSIb3DQEHAqCAMIACAQExDzANBglghkgBZQECAQUAMIAGCSqGSIb3DQEH"}}';

        $record = new LogRecord($now, 'test', Level::Info, 'nothing has changed', [
            'payment' => [
                'applePayPaymentToken' => $applePayToken
            ]
        ]);

        $result = $anonymizer($record);
        $maskedToken = $result['context']['payment']['applePayPaymentToken'];

        $this->assertEquals('**', $maskedToken);
    }
}
