<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover;

use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\shopware\Component\Payment\SubscriptionAware;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SubscriptionRemover extends PaymentMethodRemover
{
    /**
     * @var SettingsService
     */
    private $pluginSettings;

    public function __construct(ContainerInterface $container, RequestStack $requestStack, SettingsService $pluginSettings, OrderService $orderService, SettingsService $settingsService, OrderItemsExtractor $orderDataExtractor, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $orderDataExtractor, $logger);

        $this->pluginSettings = $pluginSettings;
    }

    /**
     * @throws \Exception
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        if (! $this->isAllowedRoute()) {
            return $originalData;
        }

        $settings = $this->pluginSettings->getSettings($context->getSalesChannelId());

        if (! $settings->isSubscriptionsEnabled()) {
            return $originalData;
        }

        if ($this->isOrderRoute()) {
            $order = $this->getOrder($context->getContext());
            $isSubscription = $this->isSubscriptionOrder($order, $context->getContext());
        } else {
            $cart = $this->getCart($context);
            $isSubscription = $this->isSubscriptionCart($cart);
        }

        if (! $isSubscription) {
            return $originalData;
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {
            if ($paymentMethod instanceof SubscriptionAware) {
                continue;
            }
            $originalData->getPaymentMethods()->remove($key);
        }

        return $originalData;
    }
}
