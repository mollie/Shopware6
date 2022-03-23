<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Subscription;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPaymentMethodRoute63 extends AbstractPaymentMethodRoute
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
     * @var CartService
     */
    private $cartService;

    public function __construct(
        AbstractPaymentMethodRoute $corePaymentMethodRoute,
        SystemConfigService $systemConfigService,
        CartService $cartService
    ) {
        $this->decorated = $corePaymentMethodRoute;
        $this->systemConfigService = $systemConfigService;
        $this->cartService = $cartService;
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->decorated->load($request, $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);

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
}
