<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mandate::class)]
final class MandateTest extends TestCase
{
    public function testFromClientResponse(): void
    {
        $body = [
            'id' => '123',
            'method' => 'applepay',
            'details' => ['cardNumber' => '12341234'],
        ];

        $mandate = Mandate::fromClientResponse($body);

        $this->assertEquals('123', $mandate->getId());
        $this->assertEquals(PaymentMethod::APPLEPAY, $mandate->getMethod());
        $this->assertEquals(['cardNumber' => '12341234'], $mandate->getDetails());
    }

    public function testJsonSerialize(): void
    {
        $mandate = new Mandate('123', PaymentMethod::APPLEPAY, ['token' => 'abc123']);

        $json = $mandate->jsonSerialize();

        $this->assertEquals('123', $json['id']);
        $this->assertEquals(PaymentMethod::APPLEPAY, $json['method']);
        $this->assertEquals(['token' => 'abc123'], $json['details']);
    }
}
