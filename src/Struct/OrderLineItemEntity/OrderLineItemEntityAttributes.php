<?php

namespace Kiener\MolliePayments\Struct\OrderLineItemEntity;

use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemEntityAttributes
{
    /**
     * @var OrderLineItemEntity
     */
    private $item;


    /**
     * @var string
     */
    private $voucherType;

    /**
     * @var bool
     */
    private $subscriptionProduct;

    /**
     * @var string
     */
    private $mollieOrderLineID;

    /**
     * @var int
     */
    private $subscriptionInterval;

    /**
     * @var string
     */
    private $subscriptionIntervalUnit;

    /**
     * @var ?int
     */
    private $subscriptionRepetitionCount;

    /**
     * @var bool
     */
    private $isPromotionProduct;


    /**
     * @param OrderLineItemEntity $lineItem
     */
    public function __construct(OrderLineItemEntity $lineItem)
    {
        $this->item = $lineItem;

        $this->voucherType = $this->getCustomFieldValue($lineItem, 'voucher_type');
        $this->mollieOrderLineID = $this->getCustomFieldValue($lineItem, 'order_line_id');

        $this->isPromotionProduct = $lineItem->getType() === 'promotion';

        $this->subscriptionProduct = (bool)$this->getCustomFieldValue($lineItem, 'subscription_enabled');
        $this->subscriptionInterval = (int)$this->getCustomFieldValue($lineItem, 'subscription_interval');
        $this->subscriptionIntervalUnit = (string)$this->getCustomFieldValue($lineItem, 'subscription_interval_unit');
        $this->subscriptionRepetitionCount = (int)$this->getCustomFieldValue($lineItem, 'subscription_repetition');
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

        return $this->voucherType;
    }

    /**
     * @return string
     */
    public function getMollieOrderLineID(): string
    {
        return $this->mollieOrderLineID;
    }

    /**
     * @return bool
     */
    public function isPromotionProduct(): bool
    {
        return $this->isPromotionProduct;
    }

    /**
     * @return bool
     */
    public function isSubscriptionProduct(): bool
    {
        return $this->subscriptionProduct;
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
    public function getSubscriptionIntervalUnit(): string
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
     * @return bool
     */
    public function isPromotion(): bool
    {
        if ($this->item->getPayload() === null) {
            return false;
        }

        if (isset($this->item->getPayload()['composition'])) {
            return true;
        }

        # shipping free has no composition, but we have a discount type
        if (isset($this->item->getPayload()['discountType'])) {
            return true;
        }

        return false;
    }

    /**
     * Somehow there are 2 custom fields? in payload and custom fields?
     * ....mhm...lets test always both
     * @param OrderLineItemEntity $lineItem
     * @param string $keyName
     * @return string
     */
    private function getCustomFieldValue(OrderLineItemEntity $lineItem, string $keyName): string
    {
        $foundValue = '';

        # ---------------------------------------------------------------------------
        # search in payload

        if ($lineItem->getPayload() !== null) {
            # check if we have customFields in our payload
            if (array_key_exists('customFields', $lineItem->getPayload())) {
                # load the custom fields
                $customFields = $lineItem->getPayload()['customFields'];

                if (is_array($customFields)) {
                    # ---------------------------------------------------------------------------
                    # search in new structure
                    $fullKey = 'mollie_payments_product_' . $keyName;
                    $foundValue = (array_key_exists($fullKey, $customFields)) ? (string)$customFields[$fullKey] : '';

                    # old structure
                    # check if we have a mollie entry
                    if ($foundValue === '' && array_key_exists('mollie_payments', $customFields)) {
                        # load the mollie entry
                        $mollieData = $customFields['mollie_payments'];
                        # assign our value if we have it
                        $foundValue = (array_key_exists($keyName, $mollieData)) ? (string)$mollieData[$keyName] : '';
                    }
                }
            }
        }

        # ---------------------------------------------------------------------------
        # search in custom fields

        if ($foundValue === '') {
            # check if we have customFields
            $customFields = $lineItem->getCustomFields();

            if ($customFields !== null) {
                # ---------------------------------------------------------------------------
                # search in new structure
                $fullKey = 'mollie_payments_product_' . $keyName;
                $foundValue = (array_key_exists($fullKey, $customFields)) ? (string)$customFields[$fullKey] : '';

                # old structure
                # check if we have a mollie entry
                if ($foundValue === '' && array_key_exists('mollie_payments', $customFields)) {
                    # load the mollie entry
                    $mollieData = $customFields['mollie_payments'];
                    # assign our value if we have it
                    $foundValue = (array_key_exists($keyName, $mollieData)) ? (string)$mollieData[$keyName] : '';
                }
            }
        }

        return $foundValue;
    }
}
