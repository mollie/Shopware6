<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class VoucherService
{

    /**
     * @param Cart $cart
     * @return bool
     */
    public function hasCartVoucherProducts(Cart $cart): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {

            $attributes = new LineItemAttributes($lineItem);

            if (!empty($attributes->getVoucherType())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param PaymentMethodEntity $pm
     * @return bool
     */
    public function isVoucherPaymentMethod(PaymentMethodEntity $pm): bool
    {
        $attributes = new PaymentMethodAttributes($pm);

        if ($attributes->getMolliePaymentName() === VoucherPayment::PAYMENT_METHOD_NAME) {
            return true;
        }

        return false;
    }

}
