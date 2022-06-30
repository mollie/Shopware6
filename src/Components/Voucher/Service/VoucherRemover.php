<?php

namespace Kiener\MolliePayments\Components\Voucher\Service;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Kiener\MolliePayments\Service\Cart\Voucher\VoucherService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class VoucherRemover extends PaymentMethodRemover
{
    /**
     * @var VoucherService
     */
    private $voucherService;

    /**
     * @param ContainerInterface $container
     * @param RequestStack       $requestStack
     * @param OrderService       $orderService
     * @param SettingsService    $settingsService
     * @param VoucherService     $voucherService
     * @param LoggerInterface    $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, VoucherService $voucherService, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $logger);

        $this->voucherService = $voucherService;
    }

    /**
     * @inheritDoc
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $cartService = $this->getCartServiceLazy();
        $cart = $cartService->getCart($context->getToken(), $context);

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
