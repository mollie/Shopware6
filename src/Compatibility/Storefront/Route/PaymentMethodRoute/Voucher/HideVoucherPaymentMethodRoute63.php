<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Voucher;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Kiener\MolliePayments\Service\Cart\Voucher\VoucherService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;


class HideVoucherPaymentMethodRoute63 extends AbstractPaymentMethodRoute
{

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var VoucherService
     */
    private $voucherService;

    /**
     * @param AbstractPaymentMethodRoute $corePaymentMethodRoute
     * @param CartService $cartService
     * @param VoucherService $voucherService
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, CartService $cartService, VoucherService $voucherService)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->cartService = $cartService;
        $this->voucherService = $voucherService;
    }


    /**
     * @return AbstractPaymentMethodRoute
     */
    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->corePaymentMethodRoute;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @return PaymentMethodRouteResponse
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $voucherPermitted = (bool)$cart->getData()->get(VoucherCartCollector::VOUCHER_PERMITTED);

        # if voucher is allowed, then simply continue.
        # we don't have to remove a payment method in that case
        if ($voucherPermitted) {
            return $originalData;
        }

        # now search for our voucher payment method
        # so that we can remove it from our list
        foreach ($originalData->getPaymentMethods() as $paymentMethod) {

            if ($this->voucherService->isVoucherPaymentMethod($paymentMethod)) {
                $originalData->getPaymentMethods()->remove($paymentMethod->getId());
                break;
            }
        }

        return $originalData;
    }

}
