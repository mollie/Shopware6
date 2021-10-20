<?php

namespace Kiener\MolliePayments\Struct\Product;


use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
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
        $this->voucherType = '';

        if ($product->getCustomFields() === null) {
            return;
        }

        $customFields = $product->getCustomFields();

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

}
