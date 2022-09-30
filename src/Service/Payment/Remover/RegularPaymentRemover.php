<?php

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Types\PaymentMethod;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class RegularPaymentRemover extends PaymentMethodRemover
{

    /**
     * @param ContainerInterface $container
     * @param RequestStack $requestStack
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param OrderDataExtractor $orderDataExtractor
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, OrderDataExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $orderDataExtractor, $logger);
    }

    /**
     * @inheritDoc
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (!$this->isAllowedRoute()) {
            return $originalData;
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {

            $attributes = new PaymentMethodAttributes($paymentMethod);

            # if we have SEPA Direct Debit in the list
            # then only allow it, if we have subscription products
            if ($attributes->getMollieIdentifier() === PaymentMethod::DIRECTDEBIT) {

                if (!$this->hasSubscriptionData($context)) {
                    $originalData->getPaymentMethods()->remove($key);
                }
            }
        }

        return $originalData;
    }

    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    private function hasSubscriptionData(SalesChannelContext $context): bool
    {
        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            return $this->isSubscriptionOrder($order, $context->getContext());
        } else {
            $cart = $this->getCart($context);
            return $this->isSubscriptionCart($cart);
        }
    }

}
