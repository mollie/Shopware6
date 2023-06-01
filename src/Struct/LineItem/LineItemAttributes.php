<?php

namespace Kiener\MolliePayments\Struct\LineItem;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\ProductEntity;

class LineItemAttributes
{
    /**
     * @var string
     */
    private $productNumber;

    /**
     * @var string
     */
    private $voucherType;

    /**
     * @var bool
     */
    private $subscriptionProduct;

    /**
     * @var int
     */
    private $subscriptionInterval;

    /**
     * @var string
     */
    private $subscriptionIntervalUnit;

    /**
     * @var string
     */
    private $subscriptionRepetition;

    /**
     * @var string
     */
    private $subscriptionRepetitionType;

    /**
     * @var bool
     */
    private $isPromotionProduct;

    /**
     * @param LineItem $lineItem
     */
    public function __construct(LineItem $lineItem)
    {
        $this->productNumber = '';
        $this->voucherType = '';

        $payload = $lineItem->getPayload();

        if (array_key_exists('productNumber', $payload)) {
            $this->productNumber = (string)$payload['productNumber'];
        }

        $this->isPromotionProduct = array_key_exists('promotionId', $payload);

        $this->voucherType = $this->getCustomFieldValue($lineItem, 'voucher_type');

        $this->subscriptionProduct = (bool)$this->getCustomFieldValue($lineItem, 'subscription_enabled');
        $this->subscriptionInterval = (int)$this->getCustomFieldValue($lineItem, 'subscription_interval');
        $this->subscriptionIntervalUnit = (string)$this->getCustomFieldValue($lineItem, 'subscription_interval_unit');
        $this->subscriptionRepetition = (string)$this->getCustomFieldValue($lineItem, 'subscription_repetition');
        $this->subscriptionRepetitionType = (string)$this->getCustomFieldValue($lineItem, 'subscription_repetition_type');
    }

    /**
     * @return string[]
     */
    public static function getKeyList(): array
    {
        return [
            'mollie_payments_product_voucher_type',
            'mollie_payments_product_subscription_enabled',
            'mollie_payments_product_subscription_interval',
            'mollie_payments_product_subscription_interval_unit',
            'mollie_payments_product_subscription_repetition',
            'mollie_payments_product_subscription_repetition_type',
        ];
    }

    /**
     * @return string
     */
    public function getProductNumber(): string
    {
        return $this->productNumber;
    }

    /**
     * @return bool
     */
    public function isPromotionProduct(): bool
    {
        return $this->isPromotionProduct;
    }

    /**
     * @return string
     */
    public function getVoucherType(): string
    {
        return $this->voucherType;
    }

    /**
     * @param string $voucherType
     */
    public function setVoucherType(string $voucherType): void
    {
        $this->voucherType = $voucherType;
    }

    /**
     * @return bool
     */
    public function isSubscriptionProduct(): bool
    {
        return $this->subscriptionProduct;
    }

    /**
     * @param bool $subscriptionProduct
     */
    public function setSubscriptionProduct(bool $subscriptionProduct): void
    {
        $this->subscriptionProduct = $subscriptionProduct;
    }

    /**
     * @return int
     */
    public function getSubscriptionInterval()
    {
        return $this->subscriptionInterval;
    }

    /**
     * @return string
     */
    public function getSubscriptionIntervalUnit()
    {
        return $this->subscriptionIntervalUnit;
    }

    /**
     * @return string
     */
    public function getSubscriptionRepetition()
    {
        return $this->subscriptionRepetition;
    }

    /**
     * @return string
     */
    public function getSubscriptionRepetitionType(): string
    {
        return $this->subscriptionRepetitionType;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $mollieData = [];

        $prefix = 'mollie_payments_product_';

        # lets save some space and only store
        # what is existing
        if ($this->voucherType !== null) {
            $mollieData[$prefix . 'voucher_type'] = $this->voucherType;
        }

        # only save if it's a subscription product
        if ($this->subscriptionProduct) {
            $mollieData[$prefix . 'subscription_enabled'] = $this->subscriptionProduct;

            if ($this->subscriptionInterval !== null) {
                $mollieData[$prefix . 'subscription_interval'] = $this->subscriptionInterval;
            }

            if ($this->subscriptionIntervalUnit !== null) {
                $mollieData[$prefix . 'subscription_interval_unit'] = $this->subscriptionIntervalUnit;
            }

            if ($this->subscriptionRepetition !== null) {
                $mollieData[$prefix . 'subscription_repetition'] = $this->subscriptionRepetition;
            }

            if ($this->subscriptionRepetitionType !== null) {
                $mollieData[$prefix . 'subscription_repetition_type'] = $this->subscriptionRepetitionType;
            }
        }

        return $mollieData;
    }

    /**
     * @param LineItem $lineItem
     * @param string $keyName
     * @return string
     */
    private function getCustomFieldValue(LineItem $lineItem, string $keyName): string
    {
        $foundValue = '';

        if ($lineItem->getPayload() !== null) {
            # check if we have customFields in our payload
            if (array_key_exists('customFields', $lineItem->getPayload())) {
                # load the custom fields
                $customFields = $lineItem->getPayload()['customFields'];

                # ---------------------------------------------------------------------------
                # search in new structure
                if (is_array($customFields)) {
                    $fullKey = 'mollie_payments_product_' . $keyName;
                    $foundValue = (array_key_exists($fullKey, $customFields)) ? (string)$customFields[$fullKey] : '';
                }

                # ---------------------------------------------------------------------------
                # check if old structure exists
                # and load, but we migrate to the new one
                # check if we have customFields
                if ($foundValue === '') {
                    if ($customFields !== null && array_key_exists('mollie_payments', $customFields)) {
                        # load the mollie entry
                        $mollieData = $customFields['mollie_payments'];
                        # assign our value if we have it
                        $foundValue = (array_key_exists($keyName, $mollieData)) ? (string)$mollieData[$keyName] : '';
                    }
                }
            }
        }

        return $foundValue;
    }
}
