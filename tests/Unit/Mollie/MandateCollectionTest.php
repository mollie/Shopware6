<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(MandateCollection::class)]
final class MandateCollectionTest extends TestCase
{
    public function testFilterByPaymentMethod(): void
    {
        $collection = new MandateCollection([
            new Mandate('123', PaymentMethod::APPLEPAY, []),
            new Mandate('1234', PaymentMethod::CREDIT_CARD, []),
            new Mandate('12345', PaymentMethod::APPLEPAY, []),
            new Mandate('12345', PaymentMethod::APPLEPAY, []),
        ]);

        $filtered = $collection->filterByPaymentMethod(PaymentMethod::APPLEPAY);

        $this->assertCount(3, $filtered);
    }

    public function testFilterByPaymentMethodReturnsEmptyCollection(): void
    {
        $collection = new MandateCollection([
            new Mandate('123', PaymentMethod::APPLEPAY, []),
            new Mandate('1234', PaymentMethod::CREDIT_CARD, []),
        ]);

        $filtered = $collection->filterByPaymentMethod(PaymentMethod::ALMA);

        $this->assertCount(0, $filtered);
    }
}
