<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Route;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\RefundCompositionBuilder;
use Mollie\Shopware\Component\Refund\RefundOrderLoader;
use Mollie\Shopware\Component\Refund\RefundTotalsBuilder;
use Mollie\Shopware\Component\Refund\Route\RefundOverviewRoute;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview drives the whole read path: the loader that fetches order and payment, the
 * composition that maps refunds onto the lines, and the totals that cap what is still refundable.
 * Those three are covered here because this is where they are exercised end to end.
 */
#[CoversClass(RefundOverviewRoute::class)]
#[CoversClass(RefundOrderLoader::class)]
#[CoversClass(RefundCompositionBuilder::class)]
#[CoversClass(RefundTotalsBuilder::class)]
final class RefundOverviewRouteTest extends RefundRouteTestCase
{
    public function testOverviewIsEmptyWithoutAMolliePaymentOnTheTransaction(): void
    {
        $this->givenOrder(molliePayment: null);

        $body = $this->overview();

        $this->assertSame([], $body['cart']);
        $this->assertSame(0.0, (float) $body['totals']['remaining']);
    }

    public function testOverviewRemainingIsTheRefundableTotalWithoutAnyRefund(): void
    {
        $this->givenOrder();

        $body = $this->overview();

        $this->assertSame(self::REFUNDABLE_TOTAL, (float) $body['totals']['remaining']);
        $this->assertSame(0.0, (float) $body['totals']['refunded']);
    }

    public function testOverviewRemainingSubtractsRefundedAndPendingAmounts(): void
    {
        $this->givenOrder(mollieRefunds: [
            $this->mollieRefund('re_1', 4.0, RefundStatus::Refunded),
            $this->mollieRefund('re_2', 6.0, RefundStatus::Pending),
        ]);

        $body = $this->overview();

        $this->assertSame(4.0, (float) $body['totals']['refunded']);
        $this->assertSame(6.0, (float) $body['totals']['pendingRefunds']);
        $this->assertSame(15.0, (float) $body['totals']['remaining']);
    }

    /**
     * Mollie can only refund captured money. A manual capture method that captured less than the
     * order total is the real ceiling, so the amount Mollie still accepts wins over the order total.
     */
    public function testOverviewRemainingIsCappedAtTheAmountMollieStillAccepts(): void
    {
        $freshPayment = $this->freshPayment();
        $freshPayment->setAmountRemaining(new Money(8.0, 'EUR'));

        $this->givenOrder(freshPayment: $freshPayment);

        $body = $this->overview();

        $this->assertSame(8.0, (float) $body['totals']['remaining']);
    }

    /**
     * Orders API refunds are line item based - Mollie derives the amount itself, so its payment
     * ceiling must not cap what the refund manager offers.
     */
    public function testOverviewRemainingIsNotCappedForAnOrdersApiPayment(): void
    {
        $molliePayment = $this->molliePayment();
        $molliePayment->setOrderId('ord_1');

        $freshPayment = $this->freshPayment();
        $freshPayment->setAmountRemaining(new Money(8.0, 'EUR'));

        $this->givenOrder(molliePayment: $molliePayment, freshPayment: $freshPayment);

        $body = $this->overview();

        $this->assertSame(self::REFUNDABLE_TOTAL, (float) $body['totals']['remaining']);
    }

    public function testOverviewRemainingNeverDropsBelowZero(): void
    {
        $this->givenOrder(mollieRefunds: [$this->mollieRefund('re_1', 30.0, RefundStatus::Refunded)]);

        $body = $this->overview();

        $this->assertSame(0.0, (float) $body['totals']['remaining']);
    }

    public function testOverviewCarriesTheVoucherAndRoundingAmountsOfThePayment(): void
    {
        $molliePayment = $this->molliePayment();
        $molliePayment->setVoucherAmount(7.5);
        $molliePayment->setRoundingDiff(-0.02);

        $this->givenOrder(molliePayment: $molliePayment);

        $body = $this->overview();

        $this->assertSame(7.5, (float) $body['totals']['voucherAmount']);
        $this->assertSame(-0.02, (float) $body['totals']['roundingDiff']);
    }

    public function testOverviewMarksAFullyRefundedUnitOfALineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
        $this->assertSame(10.0, (float) $item['refundedAmount']);
    }

    /**
     * A partial amount of a single unit counts as one refunded unit, so the merchant cannot refund
     * that unit a second time.
     */
    public function testOverviewCountsAPartiallyRefundedUnitAsOneRefundedUnit(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 4.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 0, amount: 4.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
    }

    public function testOverviewRefundedQuantityNeverExceedsTheLineItemQuantity(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 20.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 0, amount: 40.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(2, $item['refunded']);
    }

    public function testOverviewIgnoresACanceledRefundForTheRefundedAmountOfALineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Canceled)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(0, $item['refunded']);
        $this->assertSame(0.0, (float) $item['refundedAmount']);
    }

    /**
     * A pending refund is money that can no longer be refunded again, so it counts against the line
     * item just like a settled one.
     */
    public function testOverviewCountsAPendingRefundAgainstTheLineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Pending)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
    }

    public function testOverviewAddsTheRefundedShippingCostsToTheDelivery(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 5.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForDelivery(quantity: 1, amount: 5.0)])],
        );

        $item = $this->cartItem($this->overview(), self::DELIVERY_ID);

        $this->assertSame(5.0, (float) $item['refundedAmount']);
    }

    public function testOverviewSumsTheAmountsOfSeveralRefundsOnTheSameLineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [
                $this->mollieRefund('re_1', 10.0, RefundStatus::Refunded),
                $this->mollieRefund('re_2', 4.0, RefundStatus::Refunded),
            ],
            storedRefunds: [
                $this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)]),
                $this->storedRefund('re_2', [$this->refundItemForLineItem(quantity: 0, amount: 4.0)]),
            ],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(14.0, (float) $item['refundedAmount']);
    }

    /**
     * The Mollie refund list is what the admin renders, but the composition and the internal note
     * only exist in our own table - so the overview has to merge them onto the Mollie refund.
     */
    public function testOverviewEnrichesTheMollieRefundWithItsStoredComposition(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund(
                're_1',
                [$this->refundItemForLineItem(quantity: 1, amount: 10.0)],
                internalDescription: 'Damaged on arrival'
            )],
        );

        $body = $this->overview();

        $this->assertSame('Damaged on arrival', $body['refunds'][0]['internalDescription']);
        $this->assertCount(1, $body['refunds'][0]['metadata']['composition']);
    }

    /**
     * For a net order the refund may include the line tax, so the ceiling of a line item is its
     * total plus its tax. Measured against the net total alone, 11.90 of a 20.00 + 3.80 line would
     * already count as two refunded units instead of one.
     */
    public function testOverviewAddsTheLineTaxToTheCeilingOfANetOrder(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 11.9, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 0, amount: 11.9)])],
            taxState: CartPrice::TAX_STATE_NET,
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
    }

    public function testOverviewIgnoresAStoredRefundThatMollieDoesNotReport(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_gone', [$this->refundItemForLineItem(quantity: 2, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(0, $item['refunded']);
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(): array
    {
        $response = $this->overviewRoute()->overview(new Request([], ['orderId' => self::ORDER_ID]), $this->context);

        return $this->decode($response->getContent());
    }
}
