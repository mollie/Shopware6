<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Handler\Method\PayPalExpressPayment;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalExpressPaymentRemover extends PaymentMethodRemover
{
    /**
     * remove all payment methods,except paypal expres, if we have a paypal express auth id. hide paypal express if we havent started a paypal session yet
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (! $this->isAllowedRoute()) {
            return $originalData;
        }
        $showPPEOnly = false;

        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $showPPEOnly = (bool) ($order->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] ?? false);
        }
        if ($this->isCartRoute()) {
            $cart = $this->getCart($context);
            $mollieShopwareCart = new MollieShopwareCart($cart);

            $showPPEOnly = $mollieShopwareCart->isPayPalExpressComplete();
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {
            $attributes = new PaymentMethodAttributes($paymentMethod);
            $isPayPalExpress = $attributes->getMollieIdentifier() === PayPalExpressPayment::PAYMENT_METHOD_NAME;

            if ($showPPEOnly === true && $isPayPalExpress === false) {
                $originalData->getPaymentMethods()->remove($key);
                continue;
            }

            if ($showPPEOnly === false && $isPayPalExpress === true) {
                $originalData->getPaymentMethods()->remove($key);
            }
        }

        return $originalData;
    }
}
