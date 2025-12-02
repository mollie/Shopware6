<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class TransactionDataStruct
{
    public function __construct(
        private OrderTransactionEntity $transaction,
        private OrderEntity $order,
        private SalesChannelEntity $salesChannel,
        private CustomerEntity $customer,
        private OrderAddressEntity $shippingOrderAddress,
        private OrderAddressEntity $billingOrderAddress,
        private CurrencyEntity $currency,
        private LanguageEntity $language,
        private OrderDeliveryCollection $deliveries
    ) {
    }

    public function getTransaction(): OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getShippingOrderAddress(): OrderAddressEntity
    {
        return $this->shippingOrderAddress;
    }

    public function getBillingOrderAddress(): OrderAddressEntity
    {
        return $this->billingOrderAddress;
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function getLanguage(): LanguageEntity
    {
        return $this->language;
    }

    public function getDeliveries(): OrderDeliveryCollection
    {
        return $this->deliveries;
    }
}
