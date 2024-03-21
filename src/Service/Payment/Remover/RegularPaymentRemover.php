<?php

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
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
     * @param OrderItemsExtractor $orderDataExtractor
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, OrderItemsExtractor $orderDataExtractor, LoggerInterface $logger)
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

            # SEPA Direct Debit is only allowed when the customer updates a running subscription.
            # this means, in our plugin, it's not allowed anymore
            if ($attributes->getMollieIdentifier() === PaymentMethod::DIRECTDEBIT) {
                $originalData->getPaymentMethods()->remove($key);
            }

            # ING HomePay is deprecated
            if ($attributes->getMollieIdentifier() === PaymentMethod::INGHOMEPAY) {
                $originalData->getPaymentMethods()->remove($key);
            }

            # currently for bancomat, you cannot update the phone number, so customer might stuck in order edit. we remove the payment method for now and will enable it when the mollie api allows to change that
            if ($this->isOrderRoute() && $attributes->getMollieIdentifier() === PaymentMethod::BANCOMATPAY) {
                $originalData->getPaymentMethods()->remove($key);
            }
        }

        return $originalData;
    }
}
