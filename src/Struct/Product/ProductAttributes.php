<?php

namespace Kiener\MolliePayments\Struct\Product;

use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;

class ProductAttributes
{
    /**
     * @var ?string
     */
    private $voucherType;

    /**
     * @var ?bool
     */
    private $subscriptionProduct;

    /**
     * @var ?int
     */
    private $subscriptionInterval;

    /**
     * @var ?string
     */
    private $subscriptionIntervalUnit;

    /**
     * @var ?int
     */
    private $subscriptionRepetitionCount;


    /**
     * @param ProductEntity $product
     */
    public function __construct(ProductEntity $product)
    {
        $this->voucherType = $this->getCustomFieldValue($product, 'voucher_type');

        $this->subscriptionProduct = $this->getCustomFieldValue($product, 'subscription_enabled');
        $this->subscriptionInterval = $this->getCustomFieldValue($product, 'subscription_interval');
        $this->subscriptionIntervalUnit = $this->getCustomFieldValue($product, 'subscription_interval_unit');
        $this->subscriptionRepetitionCount = $this->getCustomFieldValue($product, 'subscription_repetition');
    }

    /**
     * @return string
     */
    public function getVoucherType()
    {
        $availableTypes = [
            VoucherType::TYPE_NONE,
            VoucherType::TYPE_ECO,
            VoucherType::TYPE_MEAL,
            VoucherType::TYPE_GIFT
        ];

        if (!in_array($this->voucherType, $availableTypes)) {
            return VoucherType::TYPE_NOTSET;
        }

        return (string)$this->voucherType;
    }

    /**
     * @return bool
     */
    public function isSubscriptionProduct(): bool
    {
        return (bool)$this->subscriptionProduct;
    }

    /**
     * @return null|int
     */
    public function getSubscriptionInterval()
    {
        return $this->subscriptionInterval;
    }

    /**
     * @return null|string
     */
    public function getSubscriptionIntervalUnit()
    {
        return $this->subscriptionIntervalUnit;
    }

    /**
     * @return null|int
     */
    public function getSubscriptionRepetitionCount()
    {
        return $this->subscriptionRepetitionCount;
    }


    /**
     * Gets a list of Mollie fields that can be removed from the
     * customFields because their value is NULL
     * @return array<mixed>
     */
    public function getRemovableFields(): array
    {
        $fields = [];

        if ($this->voucherType === null) {
            $fields[] = 'mollie_payments_product_voucher_type';
        }

        if ($this->subscriptionProduct === null) {
            $fields[] = 'mollie_payments_product_subscription_enabled';
        }

        if ($this->subscriptionInterval === null) {
            $fields[] = 'mollie_payments_product_subscription_interval';
        }

        if ($this->subscriptionIntervalUnit === null) {
            $fields[] = 'mollie_payments_product_subscription_interval_unit';
        }

        if ($this->subscriptionRepetitionCount === null) {
            $fields[] = 'mollie_payments_product_subscription_repetition';
        }

        return $fields;
    }

    /**
     * @param ProductEntity $product
     * @param string $keyName
     * @return mixed
     */
    private function getCustomFieldValue(ProductEntity $product, string $keyName)
    {
        $foundValue = '';


        $customFields = $product->getCustomFields();

        # ---------------------------------------------------------------------------
        # search in new structure
        if ($customFields !== null) {
            $fullKey = 'mollie_payments_product_' . $keyName;
            $foundValue = (array_key_exists($fullKey, $customFields)) ? $customFields[$fullKey] : null;
        }

        return $foundValue;
    }
}
