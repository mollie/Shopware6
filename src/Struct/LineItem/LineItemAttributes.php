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


        if (!array_key_exists('customFields', $lineItem->getPayload())) {
            return;
        }

        $customFields = $lineItem->getPayload()['customFields'];

        if ($customFields === null) {
            return;
        }

        if (!array_key_exists('mollie_payments', $customFields)) {
            return;
        }

        $mollieData = $customFields['mollie_payments'];

        if (array_key_exists('voucher_type', $mollieData)) {
            $this->voucherType = (string)$mollieData['voucher_type'];
        }
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
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'voucher_type' => $this->voucherType
        ];
    }

}
