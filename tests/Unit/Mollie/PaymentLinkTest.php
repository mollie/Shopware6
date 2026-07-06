<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PaymentLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentLink::class)]
final class PaymentLinkTest extends TestCase
{
    public function testCreateFromClientResponse(): void
    {
        $body = [
            'id' => 'pl_4Y0eZitmBnQ6IDoMqZQKh',
            '_links' => [
                'paymentLink' => [
                    'href' => 'https://paymentlink.mollie.com/payment/pl_4Y0eZitmBnQ6IDoMqZQKh/',
                ],
            ],
        ];

        $paymentLink = PaymentLink::createFromClientResponse($body);

        $this->assertSame('pl_4Y0eZitmBnQ6IDoMqZQKh', $paymentLink->getId());
        $this->assertSame('https://paymentlink.mollie.com/payment/pl_4Y0eZitmBnQ6IDoMqZQKh/', $paymentLink->getPaymentLinkUrl());
    }

    public function testCreateFromClientResponseWithoutUrl(): void
    {
        $paymentLink = PaymentLink::createFromClientResponse(['id' => 'pl_123']);

        $this->assertSame('pl_123', $paymentLink->getId());
        $this->assertSame('', $paymentLink->getPaymentLinkUrl());
    }
}
