<?php

namespace Kiener\MolliePayments\Struct\LineItem;


use Shopware\Core\Checkout\Cart\LineItem\LineItem;

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

        $this->voucherType = (int)$this->getCustomFieldValue($lineItem, 'voucher_type');

        $this->subscriptionProduct = (bool)$this->getCustomFieldValue($lineItem, 'subscription_product');
        $this->subscriptionInterval = (int)$this->getCustomFieldValue($lineItem, 'subscription_interval');
        $this->subscriptionIntervalUnit = (string)$this->getCustomFieldValue($lineItem, 'subscription_interval_unit');
        $this->subscriptionRepetition = (int)$this->getCustomFieldValue($lineItem, 'subscription_repetition');
        $this->subscriptionRepetitionType = (string)$this->getCustomFieldValue($lineItem, 'subscription_repetition_type');
    }

    /**
     * @return string
     */
    public function getProductNumber(): string
    {
        return $this->productNumber;
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

        # lets save some space and only store
        # what is existing
        if ($this->voucherType !== null) {
            $mollieData['voucher_type'] = $this->voucherType;
        }

        if ($this->subscriptionProduct !== null) {
            $mollieData['subscription_product'] = $this->subscriptionProduct;
        }

        if ($this->subscriptionInterval !== null) {
            $mollieData['subscription_interval'] = $this->subscriptionInterval;
        }

        if ($this->subscriptionIntervalUnit !== null) {
            $mollieData['subscription_interval_unit'] = $this->subscriptionIntervalUnit;
        }

        if ($this->subscriptionRepetition !== null) {
            $mollieData['subscription_repetition'] = $this->subscriptionRepetition;
        }

        if ($this->subscriptionRepetitionType !== null) {
            $mollieData['subscription_repetition_type'] = $this->subscriptionRepetitionType;
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
                # check if we have a mollie entry
                if ($customFields !== null && array_key_exists('mollie_payments', $customFields)) {
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
