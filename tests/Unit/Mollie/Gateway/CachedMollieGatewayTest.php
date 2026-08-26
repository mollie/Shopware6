<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
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

    public function testCreatingAPaymentAlwaysReachesTheApi(): void
    {
        $this->gateway->createPayment($this->createPayment(), self::SALES_CHANNEL);
        $this->gateway->createPayment($this->createPayment(), self::SALES_CHANNEL);

        $this->assertCount(2, $this->decorated->getCreatePayloads());
    }

    public function testCreatingAnOrderAlwaysReachesTheApi(): void
    {
        $this->gateway->createOrder($this->createOrder(), self::SALES_CHANNEL);
        $this->gateway->createOrder($this->createOrder(), self::SALES_CHANNEL);

        $this->assertCount(2, $this->decorated->getCreateOrderPayloads());
    }

    /**
     * Each capture takes money, so it must never be answered from a cache.
     */
    public function testEveryCaptureReachesTheApi(): void
    {
        $capture = new CreateCapture(new ShippingItemCollection(), 'EUR', 'Capture');

        $this->gateway->createCapture($capture, 'tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->createCapture($capture, 'tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertCount(2, $this->decorated->getCapturePayloads());
    }

    public function testEveryShipmentReachesTheApi(): void
    {
        $shipment = new CreateShipment(new ShippingItemCollection());

        $this->gateway->createShipment($shipment, 'ord_1', self::ORDER_NUMBER, self::SALES_CHANNEL);
        $this->gateway->createShipment($shipment, 'ord_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertCount(2, $this->decorated->getShipmentPayloads());
    }

    public function testCancellingOrderLinesReachesTheApi(): void
    {
        $this->gateway->cancelOrderLines('ord_1', 'odl_1', 2, self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame([[
            'mollieOrderId' => 'ord_1',
            'mollieLineId' => 'odl_1',
            'quantity' => 2,
            'orderNumber' => self::ORDER_NUMBER,
            'salesChannelId' => self::SALES_CHANNEL,
        ]], $this->decorated->getCancelledOrderLines());
    }

    public function testReleasingAnAuthorizationReachesTheApi(): void
    {
        $this->gateway->releaseAuthorization('tr_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame([[
            'paymentId' => 'tr_1',
            'orderNumber' => self::ORDER_NUMBER,
            'salesChannelId' => self::SALES_CHANNEL,
        ]], $this->decorated->getReleasedAuthorizations());
    }

    public function testRevokingAMandateReachesTheApi(): void
    {
        $this->gateway->revokeMandate('cst_1', 'mdt_1', self::SALES_CHANNEL);

        $this->assertSame([['mollieCustomerId' => 'cst_1', 'mandateId' => 'mdt_1']], $this->decorated->getRevokedMandates());
    }

    public function testRepairingALegacyTransactionAlwaysReachesTheApi(): void
    {
        $this->gateway->repairLegacyTransaction(new OrderTransactionEntity(), new OrderEntity(), Context::createDefaultContext());
        $this->gateway->repairLegacyTransaction(new OrderTransactionEntity(), new OrderEntity(), Context::createDefaultContext());

        $this->assertSame(2, $this->decorated->getRepairCallCount());
    }

    public function testSubscriptionPaymentsAreReadFromTheApi(): void
    {
        $payments = new PaymentCollection([new Payment('tr_renewal')]);
        $this->decorated->registerSubscriptionPayments('sub_1', $payments);

        $result = $this->gateway->listSubscriptionPayments('cst_1', 'sub_1', self::ORDER_NUMBER, self::SALES_CHANNEL);

        $this->assertSame($payments, $result);
    }

    /**
     * The api key check in the plugin configuration must not be answered by the profile cached for
     * the sales channel, or an invalid key would still validate.
     */
    public function testTheProfileForAnApiKeyIsReadForThatKey(): void
    {
        $this->decorated->withValidApiKey('test_valid');

        $this->assertSame('fake_profile', $this->gateway->getProfileForApiKey('test_valid')->getId());
    }

    public function testAnInvalidApiKeyIsNotHiddenByTheProfileCache(): void
    {
        $this->gateway->getCurrentProfile(self::SALES_CHANNEL);

        $this->expectException(\RuntimeException::class);

        $this->gateway->getProfileForApiKey('test_invalid');
    }

    public function testCustomersAreAlwaysCreatedAtTheApi(): void
    {
        $first = $this->gateway->createCustomer(new CustomerEntity(), self::SALES_CHANNEL);
        $second = $this->gateway->createCustomer(new CustomerEntity(), self::SALES_CHANNEL);

        $this->assertNotSame($first, $second);
    }

    /**
     * Terminals are switched on and off at Mollie, so a stale list would offer a terminal that is
     * no longer there.
     */
    public function testTerminalsAreAlwaysReadFresh(): void
    {
        $first = $this->gateway->listTerminals(self::SALES_CHANNEL);
        $second = $this->gateway->listTerminals(self::SALES_CHANNEL);

        $this->assertNotSame($first, $second);
    }

    private function createPayment(): CreatePayment
    {
        return new CreatePayment('Order ' . self::ORDER_NUMBER, 'https://shop.test/return', new Money(10.0, 'EUR'));
    }

    private function createOrder(): CreateOrder
    {
        return new CreateOrder(
            self::ORDER_NUMBER,
            'https://shop.test/return',
            new Money(10.0, 'EUR'),
            new LineItemCollection(),
            new Address('jane@shop.test', '', 'Jane', 'Doe', 'Street 1', '10115', 'Berlin', 'DE'),
            Locale::deDE
        );
    }
}
