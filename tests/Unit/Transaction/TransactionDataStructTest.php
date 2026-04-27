<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(TransactionDataStruct::class)]
final class TransactionDataStructTest extends TestCase
{
    public function testGetters(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('tx-001');

        $order = new OrderEntity();
        $order->setId('order-001');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sc-001');

        $customer = new CustomerEntity();
        $customer->setId('cust-001');

        $shippingAddress = new OrderAddressEntity();
        $shippingAddress->setId('shipping-addr-001');

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId('billing-addr-001');

        $currency = new CurrencyEntity();
        $currency->setId('curr-001');

        $language = new LanguageEntity();
        $language->setId('lang-001');

        $deliveries = new OrderDeliveryCollection();

        $struct = new TransactionDataStruct(
            $transaction,
            $order,
            $salesChannel,
            $customer,
            $shippingAddress,
            $billingAddress,
            $currency,
            $language,
            $deliveries
        );

        $this->assertSame($transaction, $struct->getTransaction());
        $this->assertSame($order, $struct->getOrder());
        $this->assertSame($salesChannel, $struct->getSalesChannel());
        $this->assertSame($customer, $struct->getCustomer());
        $this->assertSame($shippingAddress, $struct->getShippingOrderAddress());
        $this->assertSame($billingAddress, $struct->getBillingOrderAddress());
        $this->assertSame($currency, $struct->getCurrency());
        $this->assertSame($language, $struct->getLanguage());
        $this->assertSame($deliveries, $struct->getDeliveries());
    }
}
