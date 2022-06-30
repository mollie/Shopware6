<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits\Service;

use Exception;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Method;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;


class MollieLimitsRemover extends PaymentMethodRemover
{
    /**
     * @var ActivePaymentMethodsProviderInterface
     */
    private $paymentMethodsProvider;

    /**
     * @param ContainerInterface                    $container
     * @param RequestStack                          $requestStack
     * @param OrderService                          $orderService
     * @param SettingsService                       $settingsService
     * @param ActivePaymentMethodsProviderInterface $paymentMethodsProvider
     * @param LoggerInterface                       $logger
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, OrderService $orderService, SettingsService $settingsService, ActivePaymentMethodsProviderInterface $paymentMethodsProvider, LoggerInterface $logger)
    {
        parent::__construct($container, $requestStack, $orderService, $settingsService, $logger);

        $this->paymentMethodsProvider = $paymentMethodsProvider;
    }

    /**
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext        $context
     * @return PaymentMethodRouteResponse
     * @throws Exception
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        # if we do not use the limits
        # then just return everything
        if (!$settings->getUseMolliePaymentMethodLimits()) {
            return $originalData;
        }

        if (!$this->isAllowedRoute()) {
            return $originalData;
        }

        if ($this->isCartAwareRoute()) {
            $cartService = $this->getCartServiceLazy();
            $cart = $cartService->getCart($context->getToken(), $context);

            $price = $cart->getPrice()->getTotalPrice();
        }

        if ($this->isOrderAwareRoute()) {
            $request = $this->requestStack->getCurrentRequest();
            $orderId = $request->attributes->get('orderId');

            if (!$orderId) {
                return $originalData;
            }

            try {
                $order = $this->orderService->getOrder($orderId, $context->getContext());
            } catch (OrderNotFoundException $e) {
                $this->logger->error($e->getMessage(), [
                    'exception' => $e,
                    'orderId' => $orderId,
                    'paymentMethods' => $originalData,
                ]);
                return $originalData;
            }

            $price = $order->getAmountTotal();
        }

        if (!isset($price)) {
            return $originalData;
        }

        $availableMolliePayments = $this->paymentMethodsProvider->getActivePaymentMethodsForAmount(
            $price,
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
}
