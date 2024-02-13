<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Controller\Storefront\PaypalExpress\PaypalExpressControllerBase;
use Kiener\MolliePayments\Handler\Method\PayPalExpressPayment;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalExpressPaymentRemover extends PaymentMethodRemover
{
    /**
     * remove all payment methods,except paypal expres, if we have a paypal express auth id. hide paypal express if we havent started a paypal session yet
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext $context
     * @return PaymentMethodRouteResponse
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (! $this->isAllowedRoute()) {
            return $originalData;
        }
        $showPPEOnly = false;

        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $showPPEOnly = $order->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] ?? false;
        }
        if ($this->isCartRoute()) {
            $cart = $this->getCart($context);
            $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
            if ($cartExtension instanceof ArrayStruct) {
                $ppeSessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? '';
                $ppeAuthId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] ?? '';
                $showPPEOnly = mb_strlen($ppeSessionId) > 0 || mb_strlen($ppeAuthId);
            }
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
