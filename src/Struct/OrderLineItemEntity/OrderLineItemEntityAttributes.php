<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\OrderLineItemEntity;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
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
    private $productNumber;

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

    public function __construct(OrderLineItemEntity $lineItem)
    {
        $this->productNumber = '';

        $this->item = $lineItem;

        $payload = $lineItem->getPayload();

        if (is_array($payload) && array_key_exists('productNumber', $payload)) {
            $this->productNumber = (string) $payload['productNumber'];
        }

        $this->mollieOrderLineID = $this->getCustomFieldValue($lineItem, 'order_line_id');

        $this->isPromotionProduct = $lineItem->getType() === 'promotion';

        $this->subscriptionProduct = (bool) $this->getCustomFieldValue($lineItem, 'subscription_enabled');
        $this->subscriptionInterval = (int) $this->getCustomFieldValue($lineItem, 'subscription_interval');
        $this->subscriptionIntervalUnit = (string) $this->getCustomFieldValue($lineItem, 'subscription_interval_unit');
        $this->subscriptionRepetitionCount = (int) $this->getCustomFieldValue($lineItem, 'subscription_repetition');
    }

    public function getProductNumber(): string
    {
        return (string) $this->productNumber;
    }

    public function getMollieOrderLineID(): string
    {
        return $this->mollieOrderLineID;
    }

    public function isPromotionProduct(): bool
    {
        return $this->isPromotionProduct;
    }

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

    public function isPromotion(): bool
    {
        if ($this->item->getPayload() === null) {
            return false;
        }

        if (isset($this->item->getPayload()['composition'])) {
            return true;
        }

        // shipping free has no composition, but we have a discount type
        if (isset($this->item->getPayload()['discountType'])) {
            return true;
        }

        return false;
    }

    /**
     * Somehow there are 2 custom fields? in payload and custom fields?
     * ....mhm...lets test always both
     */
    private function getCustomFieldValue(OrderLineItemEntity $lineItem, string $keyName): string
    {
        $foundValue = '';
        $fullKey = 'mollie_payments_product_' . $keyName;

        // ---------------------------------------------------------------------------
        // search in payload

        if ($lineItem->getPayload() !== null) {
            // check if we have customFields in our payload
            if (array_key_exists('customFields', $lineItem->getPayload())) {
                // load the custom fields
                $customFields = $lineItem->getPayload()['customFields'];

                if (is_array($customFields)) {
                    // ---------------------------------------------------------------------------
                    // search in new structure
                    $foundValue = (array_key_exists($fullKey, $customFields)) ? (string) $customFields[$fullKey] : '';

                    // old structure
                    // check if we have a mollie entry
                    if ($foundValue === '' && array_key_exists(CustomFieldsInterface::MOLLIE_KEY, $customFields)) {
                        // load the mollie entry
                        $mollieData = $customFields[CustomFieldsInterface::MOLLIE_KEY];
                        // assign our value if we have it
                        $foundValue = (array_key_exists($keyName, $mollieData)) ? (string) $mollieData[$keyName] : '';
                    }
                }
            }
        }

        // ---------------------------------------------------------------------------
        // search in custom fields

        if ($foundValue === '') {
            // check if we have customFields
            $customFields = $lineItem->getCustomFields();

            if ($customFields !== null) {
                // ---------------------------------------------------------------------------
                // search in new structure
                $foundValue = (array_key_exists($fullKey, $customFields)) ? (string) $customFields[$fullKey] : '';

                // old structure
                // check if we have a mollie entry
                if ($foundValue === '' && array_key_exists(CustomFieldsInterface::MOLLIE_KEY, $customFields)) {
                    // load the mollie entry
                    $mollieData = $customFields[CustomFieldsInterface::MOLLIE_KEY];
                    // assign our value if we have it
                    $foundValue = (array_key_exists($keyName, $mollieData)) ? (string) $mollieData[$keyName] : '';
                }
            }
        }

        return $foundValue;
    }
}
