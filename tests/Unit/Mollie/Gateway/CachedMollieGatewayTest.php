<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(CachedMollieGateway::class)]
final class CachedMollieGatewayTest extends TestCase
{
    private const ORDER_NUMBER = '10001';
    private const SALES_CHANNEL = 'sales-channel-1';

    private FakeGateway $decorated;

    private CachedMollieGateway $gateway;

    protected function setUp(): void
    {
        $this->decorated = new FakeGateway('', new Payment('tr_1'));
        $this->gateway = new CachedMollieGateway($this->decorated);
    }

    public function testTheSamePaymentIsAskedForOnlyOnce(): void
    {
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame(1, $this->decorated->getCallCount('getPayment'));
    }

    public function testADifferentPaymentIsFetchedOnItsOwn(): void
    {
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getPayment('tr_2', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getPayment'));
    }

    public function testTheSameTransactionIsAskedForOnlyOnce(): void
    {
        $context = Context::createDefaultContext();

        $this->gateway->getPaymentByTransactionId('transaction-1', $context);
        $this->gateway->getPaymentByTransactionId('transaction-1', $context);

        $this->assertSame(1, $this->decorated->getCallCount('getPaymentByTransactionId'));
    }

    public function testTheSameOrderIsAskedForOnlyOnce(): void
    {
        $this->gateway->getOrder('ord_1', self::SALES_CHANNEL);
        $this->gateway->getOrder('ord_1', self::SALES_CHANNEL);

        $this->assertSame(1, $this->decorated->getCallCount('getOrder'));
    }

    public function testTheProfileIsAskedForOncePerSalesChannel(): void
    {
        $this->gateway->getCurrentProfile(self::SALES_CHANNEL);
        $this->gateway->getCurrentProfile(self::SALES_CHANNEL);
        $this->gateway->getCurrentProfile('sales-channel-2');

        $this->assertSame(2, $this->decorated->getCallCount('getCurrentProfile'));
    }

    public function testAnUpdatedPaymentIsReadFreshSoTheStatusIsNotStale(): void
    {
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->updatePayment('tr_1', new CreatePayment('Order 10001', 'https://shop.test/return', new Money(10.0, 'EUR')), self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getPayment'));
    }

    public function testACancelledPaymentIsReadFreshSoTheStatusIsNotStale(): void
    {
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->cancelPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getPayment'));
    }

    public function testCancellingOnePaymentDoesNotDropTheOthersFromTheCache(): void
    {
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getPayment('tr_2', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->cancelPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getPayment('tr_2', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getPayment'));
    }

    public function testACancelledOrderIsReadFreshSoTheStatusIsNotStale(): void
    {
        $this->gateway->getOrder('ord_1', self::SALES_CHANNEL);
        $this->gateway->cancelOrder('ord_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getOrder('ord_1', self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getOrder'));
    }

    public function testAvailableMethodsAreCachedForTheSameCart(): void
    {
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);

        $this->assertSame(1, $this->decorated->getCallCount('getActivePaymentMethods'));
    }

    public function testADifferentCartTotalGetsItsOwnListOfMethods(): void
    {
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);
        $this->gateway->getActivePaymentMethods(new Money(500.0, 'EUR'), 'DE', self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getActivePaymentMethods'));
    }

    public function testADifferentCurrencyGetsItsOwnListOfMethods(): void
    {
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'CHF'), 'DE', self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getActivePaymentMethods'));
    }

    public function testADifferentBillingCountryGetsItsOwnListOfMethods(): void
    {
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'NL', self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getActivePaymentMethods'));
    }

    public function testADifferentSalesChannelGetsItsOwnListOfMethods(): void
    {
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', 'sales-channel-2');

        $this->assertSame(2, $this->decorated->getCallCount('getActivePaymentMethods'));
    }

    public function testEverythingIsReadFreshAfterTheCacheWasCleared(): void
    {
        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getOrder('ord_1', self::SALES_CHANNEL);
        $this->gateway->getPaymentByTransactionId('transaction-1', Context::createDefaultContext());
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);

        $this->gateway->clearCache();

        $this->gateway->getPayment('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->getOrder('ord_1', self::SALES_CHANNEL);
        $this->gateway->getPaymentByTransactionId('transaction-1', Context::createDefaultContext());
        $this->gateway->getActivePaymentMethods(new Money(10.0, 'EUR'), 'DE', self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('getPayment'));
        $this->assertSame(2, $this->decorated->getCallCount('getOrder'));
        $this->assertSame(2, $this->decorated->getCallCount('getPaymentByTransactionId'));
        $this->assertSame(2, $this->decorated->getCallCount('getActivePaymentMethods'));
    }

    public function testMandatesAreAlwaysReadFreshBecauseTheyAreNotCached(): void
    {
        $this->gateway->listMandates('cst_1', self::SALES_CHANNEL);
        $this->gateway->listMandates('cst_1', self::SALES_CHANNEL);

        $this->assertSame(2, $this->decorated->getCallCount('listMandates'));
    }
}
