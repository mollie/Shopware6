<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentCollection::class)]
final class PaymentCollectionTest extends TestCase
{
    public function testOnlyPaymentsMollieAllowsToCancelAreKept(): void
    {
        $collection = new PaymentCollection([
            $this->payment('tr-1', true),
            $this->payment('tr-2', false),
            $this->payment('tr-3', true),
        ]);

        $cancelable = $collection->filterCancelable();

        $this->assertSame(['tr-1', 'tr-3'], array_map(static fn (Payment $p): string => $p->getId(), array_values($cancelable->getElements())));
    }

    public function testNoCancelablePaymentLeavesAnEmptyCollection(): void
    {
        $collection = new PaymentCollection([$this->payment('tr-1', false)]);

        $this->assertCount(0, $collection->filterCancelable());
    }

    private function payment(string $id, bool $cancelable): Payment
    {
        $payment = new Payment($id);
        $payment->setCancelable($cancelable);

        return $payment;
    }
}
