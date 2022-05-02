<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits\Service;

use Exception;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Method;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CheckoutController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Throwable;


class MollieLimitsRemover
{

    /**
     * @var Container
     */
    private $container;

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var ActivePaymentMethodsProviderInterface
     */
    private $paymentMethodsProvider;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param Container                             $container
     * @param SettingsService                       $pluginSettings
     * @param ActivePaymentMethodsProviderInterface $paymentMethodsProvider
     * @param RequestStack                          $requestStack
     * @param LoggerInterface                       $logger
     */
    public function __construct(Container $container, SettingsService $pluginSettings, ActivePaymentMethodsProviderInterface $paymentMethodsProvider, RequestStack $requestStack, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->pluginSettings = $pluginSettings;
        $this->paymentMethodsProvider = $paymentMethodsProvider;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext        $context
     * @return PaymentMethodRouteResponse
     * @throws Exception
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $settings = $this->pluginSettings->getSettings($context->getSalesChannelId());

        # if we do not use the limits
        # then just return everything
        if (!$settings->getUseMolliePaymentMethodLimits()) {
            return $originalData;
        }

        if (!$this->isRemovingAllowedInContext()) {
            return $originalData;
        }

        $cartService = $this->getCartServiceLazy();
        $cart = $cartService->getCart($context->getToken(), $context);


        $availableMolliePayments = $this->paymentMethodsProvider->getActivePaymentMethodsForAmount(
            $cart,
            $context->getCurrency()->getIsoCode(),
            [
                $context->getSalesChannel()->getId(),
            ]
        );


        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($originalData->getPaymentMethods() as $paymentMethod) {

            $mollieAttributes = new PaymentMethodAttributes($paymentMethod);

            # check if we have even a mollie payment
            # if not, then always keep that payment method
            if (!$mollieAttributes->isMolliePayment()) {
                continue;
            }

            $found = false;

            # now search if we still have it, otherwise just remove it
            /** @var Method $mollieMethod */
            foreach ($availableMolliePayments as $mollieMethod) {
                # if we have found it in the list of available mollie methods
                # then just keep it
                if ($mollieMethod->id == $mollieAttributes->getMollieIdentifier()) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $originalData->getPaymentMethods()->remove($paymentMethod->getId());
            }
        }

        return $originalData;
    }

    /**
     * We have to use lazy loading for this. Otherwise there are plugin compatibilities
     * with a circular reference...even though XML looks fine.
     *
     * @return CartService
     * @throws Exception
     */
    private function getCartServiceLazy(): CartService
    {
        $service = $this->container->get('Shopware\Core\Checkout\Cart\SalesChannel\CartService');

        if (!$service instanceof CartService) {
            throw new Exception('CartService of Shopware not found!');
        }

        return $service;
    }

    /**
     * This tries to get the current route/controller from the current request to determine if we're in the checkout.
     * The CheckoutController encompasses the following routes:
     * GET /checkout/cart
     * GET /checkout/confirm
     * GET /checkout/finish
     * POST /checkout/order
     * GET /widgets/checkout/info
     * GET /checkout/offcanvas
     *
     * @return bool
     */
    private function isRemovingAllowedInContext(): bool
    {
        try {
            $request = $this->requestStack->getCurrentRequest();

            if (!$request instanceof Request) {
                return false;
            }

            # we also need to allow removing for store-api calls
            # this is for the headless approach
            if (strpos($request->getPathInfo(), '/store-api') === 0) {
                return true;
            }

            $controller = current(explode('::', $request->attributes->get('_controller')));

            return $controller === CheckoutController::class;

        } catch (Throwable $e) {

            $this->logger
                ->error('An error occurred determining current controller', [
                    'exception' => $e,
                    'request' => $request ?? null,
                ]);

            // Make sure Shopware will behave normally in the case of an error.
            return false;
        }
    }

}
