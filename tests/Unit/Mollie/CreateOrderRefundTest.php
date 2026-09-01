<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\CreateOrderRefund;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateOrderRefund::class)]
final class CreateOrderRefundTest extends TestCase
{
    public function testTheOrderTheRefundBelongsToIsKept(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection([$this->lineItem('odl_1', 2)]));

        $this->assertSame('ord_1', $refund->getOrderId());
    }

    public function testTheLinesTheRefundWasBuiltFromAreKept(): void
    {
        $line = $this->lineItem('odl_1', 2);

        $refund = new CreateOrderRefund('ord_1', new LineItemCollection([$line]));

        $this->assertSame([$line], array_values($refund->getLines()->getElements()));
    }

    /**
     * Mollie recalculates the amount from the line itself, so only the line id and the quantity
     * to refund are sent.
     */
    public function testALineIsSentWithItsMollieIdAndQuantity(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection([$this->lineItem('odl_1', 2)]));

        $this->assertSame([['id' => 'odl_1', 'quantity' => 2]], $refund->toArray()['lines']);
    }

    public function testEveryLineIsSent(): void
    {
        $lines = new LineItemCollection([$this->lineItem('odl_1', 1), $this->lineItem('odl_2', 3)]);

        $refund = new CreateOrderRefund('ord_1', $lines);

        $this->assertSame([
            ['id' => 'odl_1', 'quantity' => 1],
            ['id' => 'odl_2', 'quantity' => 3],
        ], $refund->toArray()['lines']);
    }

    /**
     * A refund without lines is a full refund at Mollie - sending an empty lines array would be
     * rejected instead.
     */
    public function testARefundWithoutLinesSendsNoLinesKey(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection());

        $this->assertArrayNotHasKey('lines', $refund->toArray());
    }

    public function testTheDescriptionIsSentWhenTheMerchantEnteredOne(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection());
        $refund->setDescription('Damaged on arrival');

        $this->assertSame('Damaged on arrival', $refund->toArray()['description']);
    }

    public function testAnEmptyDescriptionIsNotSent(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection());

        $this->assertArrayNotHasKey('description', $refund->toArray());
    }

    public function testTheMetadataIsSentWhenThereIsAny(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection());
        $refund->setMetadata(['orderNumber' => '10001']);

        $this->assertSame(['orderNumber' => '10001'], $refund->toArray()['metadata']);
    }

    public function testEmptyMetadataIsNotSent(): void
    {
        $refund = new CreateOrderRefund('ord_1', new LineItemCollection());

        $this->assertArrayNotHasKey('metadata', $refund->toArray());
    }

    private function lineItem(string $mollieLineId, int $quantity): LineItem
    {
        $lineItem = new LineItem('Product A', $quantity, new Money(10.0, 'EUR'), new Money(10.0 * $quantity, 'EUR'));
        $lineItem->setId($mollieLineId);

        return $lineItem;
    }
}
