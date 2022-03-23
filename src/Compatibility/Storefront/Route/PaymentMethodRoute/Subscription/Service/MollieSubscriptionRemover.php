<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Subscription\Service;


use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Container;

class MollieSubscriptionRemover
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
    ];


    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var Container
     */
    private $container;


    /**
     * @param Container $container
     * @param SettingsService $pluginSettings
     */
    public function __construct(Container $container, SettingsService $pluginSettings)
    {
        $this->container = $container;
        $this->pluginSettings = $pluginSettings;
    }


    /**
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext $context
     * @return PaymentMethodRouteResponse
     * @throws \Exception
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $settings = $this->pluginSettings->getSettings();

        if (!$settings->isSubscriptionsEnableBeta()) {
            return $originalData;
        }

        $cart = $this->getCartServiceLazy()->getCart($context->getToken(), $context);

        if (!$this->isSubscriptionCart($cart)) {
            return $originalData;
        }

        foreach ($originalData->getPaymentMethods() as $key => $paymentMethod) {
            $paymentMethodName = $paymentMethod->getTranslation('customFields')['mollie_payment_method_name'] ?? '';
            if (!in_array($paymentMethodName, self::ALLOWED_METHODS)) {
                $originalData->getPaymentMethods()->remove($key);
            }
        }

        return $originalData;
    }

    /**
     * @param Cart $cart
     * @return bool
     */
    private function isSubscriptionCart(Cart $cart): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            $customFields = $lineItem->getPayload()['customFields'];
            if (isset($customFields["mollie_subscription"]['mollie_subscription_product'])
                && $customFields["mollie_subscription"]['mollie_subscription_product']) {
                return true;
            }
        }

        return false;
    }

    /**
     * We have to use lazy loading for this. Otherwise there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @return CartService
     * @throws \Exception
     */
    private function getCartServiceLazy(): CartService
    {
        $service = $this->container->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');

        if (!$service instanceof CartService) {
            throw new \Exception('CartService of Shopware not found!');
        }

        return $service;
    }

}
