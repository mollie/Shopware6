<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Subscription;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPaymentMethodRoute62 extends AbstractPaymentMethodRoute
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
     * @var AbstractPaymentMethodRoute
     */
    private $decorated;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var Container
     */
    private $container;

    public function __construct(
        AbstractPaymentMethodRoute $corePaymentMethodRoute,
        SystemConfigService $systemConfigService,
        Container $container
    ) {
        $this->decorated = $corePaymentMethodRoute;
        $this->systemConfigService = $systemConfigService;
        $this->container = $container;
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    /**
     * ATTENTION:
     * This works in Shopware 6.2, but only for the API.
     * The confirm page does not use this approach, and thus the voucher
     * payment method is not automatically hidden if not available.
     *
     * @param Request $request
     * @param SalesChannelContext $context
     * @return PaymentMethodRouteResponse
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->decorated->load($request, $context);

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

    private function isSubscriptionCart(Cart $cart): bool
    {
        if (!$this->systemConfigService->get('MolliePayments.config.enableSubscriptions')) {
            return false;
        }

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
