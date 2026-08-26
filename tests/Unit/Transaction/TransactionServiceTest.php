<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Mollie\LineItem as MollieLineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order as MollieOrder;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Transaction\TransactionDataException;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\CustomerEntityBuilder;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(TransactionService::class)]
final class TransactionServiceTest extends TestCase
{
    public function testOrderCustomFieldsContainJtlMappedKeys(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $service->savePaymentExtension('transactionId', $this->buildOrder(), $this->buildPayment(), new Context(new SystemSource()));

        $upsert = $repository->getUpserts()[0];
        $orderExtension = $upsert['order']['customFields'][Mollie::EXTENSION];

        $this->assertSame('ord_orderId', $orderExtension['order_id']);
        $this->assertSame('tr_paymentId', $orderExtension['payment_id']);
        $this->assertSame('paypal_thirdParty', $orderExtension['third_party_payment_id']);
    }

    public function testTransactionCustomFieldsContainLegacyMappedKeys(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $service->savePaymentExtension('transactionId', $this->buildOrder(), $this->buildPayment(), new Context(new SystemSource()));

        $upsert = $repository->getUpserts()[0];
        $transactionExtension = $upsert['customFields'][Mollie::EXTENSION];

        $this->assertSame('ord_orderId', $transactionExtension['order_id']);
        $this->assertSame('tr_paymentId', $transactionExtension['payment_id']);
        $this->assertSame('paypal_thirdParty', $transactionExtension['third_party_payment_id']);
    }

    public function testUnknownTransactionIsRejected(): void
    {
        $service = new TransactionService(new FakeOrderTransactionRepository(), new FakeLogger());

        $this->expectException(TransactionDataException::class);
        $service->findById('unknown-transaction-id', new Context(new SystemSource()));
    }

    public function testTransactionIdIsLoweredBeforeTheLookup(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $repository->withTransaction($this->buildTransaction());
        $service = new TransactionService($repository, new FakeLogger());

        $service->findById('ABCDEF', new Context(new SystemSource()));

        $this->assertSame(['abcdef'], $repository->getSearchCriteria()[0]->getIds());
    }

    public function testTransactionWithoutOrderIsRejected(): void
    {
        $transactionWithoutOrder = new OrderTransactionEntity();
        $transactionWithoutOrder->setId('transaction-id');

        $repository = new FakeOrderTransactionRepository();
        $repository->withTransaction($transactionWithoutOrder);
        $service = new TransactionService($repository, new FakeLogger());

        $this->expectException(TransactionDataException::class);
        $service->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderWithoutSalesChannelIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withSalesChannel: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderWithoutDeliveriesIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withDeliveries: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderWithoutLanguageIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withLanguage: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderWithoutCurrencyIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withCurrency: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderWithoutBillingAddressIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withBillingAddress: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderWithoutCustomerIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withOrderCustomer: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testOrderCustomerWithoutCustomerIsRejected(): void
    {
        $this->expectException(TransactionDataException::class);
        $this->makeService($this->buildTransaction(withCustomer: false))->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testDeliveryWithoutShippingAddressIsRejected(): void
    {
        $transaction = $this->buildTransaction();
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-without-address');
        $transaction->getOrder()->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $transaction->getOrder()->setPrimaryOrderDelivery($delivery);

        $this->expectException(TransactionDataException::class);
        $this->makeService($transaction)->findById('transaction-id', new Context(new SystemSource()));
    }

    public function testTransactionDataIsCollected(): void
    {
        $transaction = $this->buildTransaction();
        $delivery = $transaction->getOrder()->getDeliveries()->first();
        $delivery->getShippingOrderAddress()->setCity('Delivery City');
        $transaction->getOrder()->setPrimaryOrderDelivery($delivery);

        $data = $this->makeService($transaction)->findById('transaction-id', new Context(new SystemSource()));

        $this->assertSame($transaction, $data->getTransaction());
        $this->assertSame('10000', $data->getOrder()->getOrderNumber());
        $this->assertSame('sales-channel-id', $data->getSalesChannel()->getId());
        $this->assertSame('EUR', $data->getCurrency()->getIsoCode());
        $this->assertSame('de-DE', $data->getLanguage()->getLocale()->getCode());
        $this->assertSame('Delivery City', $data->getShippingOrderAddress()->getCity());
        $this->assertSame('Test City', $data->getBillingOrderAddress()->getCity());
        $this->assertCount(2, $data->getDeliveries());
    }

    public function testDigitalOrderWithoutDeliveryFallsBackToTheBillingAddress(): void
    {
        $transaction = $this->buildTransaction();
        $transaction->getOrder()->setDeliveries(new OrderDeliveryCollection());
        $transaction->getOrder()->getBillingAddress()->setCity('Billing City');

        $data = $this->makeService($transaction)->findById('transaction-id', new Context(new SystemSource()));

        $this->assertSame('Billing City', $data->getShippingOrderAddress()->getCity());
    }

    public function testMollieLineIdsArePersistedOnTheOrderLineItems(): void
    {
        // The shipment flow needs the Mollie line id per Shopware line item; without it a shipment
        // cannot tell Mollie which line was shipped.
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $lineItem = (new OrderEntityBuilder())->createShippableLineItem('lineitemid', 'SW100', 1, 10.0);
        $order = $this->buildOrder();
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        $service->savePaymentExtension(
            'transactionId',
            $order,
            $this->buildPayment(),
            new Context(new SystemSource()),
            new MollieOrder('ord_orderId', '', null, [$this->mollieLine('odl_1', 'lineitemid')]),
        );

        $upsert = $repository->getUpserts()[0];
        $this->assertSame([[
            'id' => 'lineitemid',
            'customFields' => [Mollie::EXTENSION => ['order_line_id' => 'odl_1']],
        ]], $upsert['order']['lineItems']);
    }

    public function testMollieLineIdsArePersistedOnTheDeliveries(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $delivery = (new OrderEntityBuilder())->createShippableDelivery('deliveryid', 'lineitemid');
        $order = $this->buildOrder();
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $service->savePaymentExtension(
            'transactionId',
            $order,
            $this->buildPayment(),
            new Context(new SystemSource()),
            new MollieOrder('ord_orderId', '', null, [$this->mollieLine('odl_delivery', 'deliveryid')]),
        );

        $upsert = $repository->getUpserts()[0];
        $this->assertSame([[
            'id' => 'deliveryid',
            'customFields' => [Mollie::EXTENSION => ['order_line_id' => 'odl_delivery']],
        ]], $upsert['order']['deliveries']);
    }

    public function testMollieLinesThatBelongToNoShopwareLineAreIgnored(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $service->savePaymentExtension(
            'transactionId',
            $this->buildOrder(),
            $this->buildPayment(),
            new Context(new SystemSource()),
            new MollieOrder('ord_orderId', '', null, [$this->mollieLine('odl_1', 'unknownlineitemid')]),
        );

        $upsert = $repository->getUpserts()[0];
        $this->assertArrayNotHasKey('lineItems', $upsert['order']);
        $this->assertArrayNotHasKey('deliveries', $upsert['order']);
    }

    private function makeService(OrderTransactionEntity $transaction): TransactionService
    {
        $repository = new FakeOrderTransactionRepository();
        $repository->withTransaction($transaction);

        return new TransactionService($repository, new FakeLogger());
    }

    /**
     * Shopware's order setters are not nullable, so a missing association is modelled by never
     * assigning it - not by assigning null.
     */
    private function buildTransaction(
        bool $withSalesChannel = true,
        bool $withDeliveries = true,
        bool $withLanguage = true,
        bool $withCurrency = true,
        bool $withBillingAddress = true,
        bool $withOrderCustomer = true,
        bool $withCustomer = true
    ): OrderTransactionEntity {
        $customer = (new CustomerEntityBuilder())->getDefaultCustomer();
        $orderBuilder = new OrderEntityBuilder();

        $order = new OrderEntity();
        $order->setId('fakeShopwareOrderId');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('sales-channel-id');

        if ($withSalesChannel) {
            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId('sales-channel-id');
            $order->setSalesChannel($salesChannel);
        }

        if ($withDeliveries) {
            $order->setDeliveries($orderBuilder->getOrderDeliveries($customer));
        }

        if ($withLanguage) {
            $locale = new LocaleEntity();
            $locale->setCode('de-DE');
            $language = new LanguageEntity();
            $language->setLocale($locale);
            $order->setLanguage($language);
        }

        if ($withCurrency) {
            $currency = new CurrencyEntity();
            $currency->setIsoCode('EUR');
            $order->setCurrency($currency);
        }

        if ($withBillingAddress) {
            $order->setBillingAddress($orderBuilder->getOrderAddress($customer));
        }

        if ($withOrderCustomer) {
            $orderCustomer = new OrderCustomerEntity();
            $orderCustomer->setId('order-customer-id');
            if ($withCustomer) {
                $orderCustomer->setCustomer($customer);
            }
            $order->setOrderCustomer($orderCustomer);
        }

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');
        $transaction->setOrder($order);

        return $transaction;
    }

    private function buildPayment(): Payment
    {
        $payment = new Payment('tr_paymentId');
        $payment->setOrderId('ord_orderId');
        $payment->setThirdPartyPaymentId('paypal_thirdParty');
        $payment->setMethod(PaymentMethod::PAYPAL);
        $payment->setStatus(PaymentStatus::PAID);

        return $payment;
    }

    private function buildOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('shopwareOrderId');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('salesChannelId');

        return $order;
    }

    private function mollieLine(string $mollieLineId, string $shopwareLineItemId): MollieLineItem
    {
        $line = new MollieLineItem('Product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setId($mollieLineId);
        $line->setShopwareLineItemId($shopwareLineItemId);

        return $line;
    }
}
