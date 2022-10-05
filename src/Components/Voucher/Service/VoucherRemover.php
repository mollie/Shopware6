<?php

namespace Kiener\MolliePayments\Components\Voucher\Service;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Kiener\MolliePayments\Service\Cart\Voucher\VoucherService;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\Context;
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
     * @param RequestStack $requestStack
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param VoucherService $voucherService
     * @param OrderDataExtractor $orderDataExtractor
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, VoucherService $voucherService, OrderDataExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $orderDataExtractor, $logger);

        $this->voucherService = $voucherService;
    }

    /**
     * @inheritDoc
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (!$this->isAllowedRoute()) {
            return $originalData;
        }

        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $voucherPermitted = $this->isVoucherOrder($order, $context->getContext());
        } else {
            $cart = $this->getCart($context);
            $voucherPermitted = (bool)$cart->getData()->get(VoucherCartCollector::VOUCHER_PERMITTED);
        }


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
