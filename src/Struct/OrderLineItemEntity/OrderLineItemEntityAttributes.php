<?php

namespace Kiener\MolliePayments\Struct\OrderLineItemEntity;

use Kiener\MolliePayments\Struct\OrderXEntityAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class OrderLineItemEntityAttributes extends OrderXEntityAttributes
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
     * @var OrderLineItemEntity
     */
    private $item;


    /**
     * @param OrderLineItemEntity $entity
     */
    public function __construct(OrderLineItemEntity $entity)
    {
        parent::__construct($entity);
        $this->item = $entity;
        $this->voucherType = $this->getCustomFieldValue($entity, 'voucher_type');

        $this->subscriptionProduct = (bool)$this->getCustomFieldValue($entity, 'subscription_enabled');
        $this->subscriptionInterval = (int)$this->getCustomFieldValue($entity, 'subscription_interval');
        $this->subscriptionIntervalUnit = (string)$this->getCustomFieldValue($entity, 'subscription_interval_unit');
        $this->subscriptionRepetitionCount = (int)$this->getCustomFieldValue($entity, 'subscription_repetition');

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

    protected function getCustomFieldValue(Entity $entity, string $keyName): string
    {
        $foundValue = '';

        # ---------------------------------------------------------------------------
        # first search in payload

        if ($entity instanceof OrderLineItemEntity && $entity->getPayload() !== null) {
            # check if we have customFields in our payload
            if (array_key_exists('customFields', $entity->getPayload())) {
                # load the custom fields
                $customFields = $entity->getPayload()['customFields'];

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

        if ($foundValue == '') {
            $foundValue = parent::getCustomFieldValue($entity, $keyName);
        }

        return $foundValue;
    }
}
