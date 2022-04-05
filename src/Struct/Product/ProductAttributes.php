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
     * @var string
     */
    private $voucherType;


    /**
     * @param ProductEntity $product
     */
    public function __construct(ProductEntity $product)
    {
        $this->voucherType = $this->getCustomFieldValue($product, 'voucher_type');
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
     * @param ProductEntity $product
     * @param string $keyName
     * @return string
     */
    private function getCustomFieldValue(ProductEntity $product, string $keyName): string
    {
        $foundValue = '';


        $customFields = $product->getCustomFields();

        # ---------------------------------------------------------------------------
        # search in new structure
        if ($customFields !== null) {
            $fullKey = 'mollie_payments.product.' . $keyName;
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

        return $foundValue;
    }
}
