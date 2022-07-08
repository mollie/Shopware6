<?php

namespace Kiener\MolliePayments\Struct\OrderLineItemEntity;


use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemEntityAttributes
{

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
     * @var int
     */
    private $resetStockQuantity;


    /**
     * @param OrderLineItemEntity $lineItem
     */
    public function __construct(OrderLineItemEntity $lineItem)
    {
        $this->voucherType = $this->getCustomFieldValue($lineItem, 'voucher_type');
        $this->mollieOrderLineID = $this->getCustomFieldValue($lineItem, 'order_line_id');
        $this->resetStockQuantity = (int)$this->getCustomFieldValue($lineItem, 'reset_stock_quantity');

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
     * @return int
     */
    public function getResetStockQuantity(): int
    {
        return $this->resetStockQuantity;
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
     * @return int|null
     */
    public function getSubscriptionRepetitionCount()
    {
        return $this->subscriptionRepetitionCount;
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

                # ---------------------------------------------------------------------------
                # search in new structure
                $fullKey = 'mollie_payments_product_' . $keyName;
                $foundValue = (array_key_exists($fullKey, $customFields)) ? (string)$customFields[$fullKey] : '';

                # old structure
                # check if we have a mollie entry
                if ($foundValue === '' && $customFields !== null && array_key_exists('mollie_payments', $customFields)) {
                    # load the mollie entry
                    $mollieData = $customFields['mollie_payments'];
                    # assign our value if we have it
                    $foundValue = (array_key_exists($keyName, $mollieData)) ? (string)$mollieData[$keyName] : '';
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
