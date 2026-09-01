<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePaymentRefund::class)]
final class CreatePaymentRefundTest extends TestCase
{
    public function testThePaymentTheRefundBelongsToIsKept(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'));

        $this->assertSame('tr_1', $refund->getPaymentId());
    }

    public function testTheAmountToRefundIsKept(): void
    {
        $amount = new Money(10.0, 'EUR');

        $refund = new CreatePaymentRefund('tr_1', $amount);

        $this->assertSame($amount, $refund->getAmount());
    }

    /**
     * Mollie needs the amount as a two-decimal string in the payment's own currency.
     */
    public function testTheAmountIsSentInMolliesFormat(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.5, 'EUR'));

        $this->assertSame(['value' => '10.50', 'currency' => 'EUR'], $refund->toArray()['amount']);
    }

    /**
     * The description shows up on the customer's bank statement, so it is always sent - even empty.
     */
    public function testTheDescriptionTheMerchantEnteredIsSent(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'), 'Damaged on arrival');

        $this->assertSame('Damaged on arrival', $refund->toArray()['description']);
    }

    public function testADescriptionSetAfterwardsWins(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'), 'Initial');

        $refund->setDescription('Corrected');

        $this->assertSame('Corrected', $refund->toArray()['description']);
    }

    public function testTheMetadataIsSentWhenThereIsAny(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'));
        $refund->setMetadata(['orderNumber' => '10001']);

        $this->assertSame(['orderNumber' => '10001'], $refund->toArray()['metadata']);
    }

    public function testEmptyMetadataIsNotSent(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'));

        $this->assertArrayNotHasKey('metadata', $refund->toArray());
    }

    /**
     * A payment refund is not made of order lines - Mollie refunds the amount as a whole.
     */
    public function testAPaymentRefundCarriesNoLines(): void
    {
        $refund = new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR'));

        $this->assertSame(0, $refund->getLines()->count());
    }
}
