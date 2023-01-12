<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover;

use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SubscriptionRemover extends PaymentMethodRemover
{
    public const ALLOWED_METHODS = [
        'ideal',
        'bancontact',
        'sofort',
        'eps',
        'giropay',
        'belfius',
        'creditcard',
        'paypal',
        'directdebit',
    ];

    /**
     * @var SettingsService
     */
    private $pluginSettings;


    /**
     * @param ContainerInterface $container
     * @param RequestStack $requestStack
     * @param SettingsService $pluginSettings
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param OrderDataExtractor $orderDataExtractor
     * @param LoggerInterface $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, SettingsService $pluginSettings, OrderService $orderService, SettingsService $settingsService, OrderDataExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $orderDataExtractor, $logger);

        $this->pluginSettings = $pluginSettings;
    }

    /**
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return PaymentMethodRouteResponse
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (!$this->isAllowedRoute()) {
            return $originalData;
        }

        $settings = $this->pluginSettings->getSettings($context->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            return $originalData;
        }


        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $isSubscription = $this->isSubscriptionOrder($order, $context->getContext());
        } else {
            $cart = $this->getCart($context);
            $isSubscription = $this->isSubscriptionCart($cart);
        }


        if (!$isSubscription) {
            return $originalData;
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {
            $attributes = new PaymentMethodAttributes($paymentMethod);

            $paymentMethodName = $attributes->getMollieIdentifier();

            if (!in_array($paymentMethodName, self::ALLOWED_METHODS)) {
                $originalData->getPaymentMethods()->remove($key);
            }
        }

        return $originalData;
    }
}
