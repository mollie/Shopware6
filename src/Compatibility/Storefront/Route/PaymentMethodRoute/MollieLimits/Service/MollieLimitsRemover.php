<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits\Service;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Kiener\MolliePayments\Service\Cart\Voucher\VoucherService;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;


class MollieLimitsRemover
{

    /**
     * @var ActivePaymentMethodsProviderInterface
     */
    private $paymentMethodsProvider;

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
     * @param ActivePaymentMethodsProviderInterface $paymentMethodsProvider
     */
    public function __construct( Container $container, SettingsService $pluginSettings, ActivePaymentMethodsProviderInterface $paymentMethodsProvider)
    {
        $this->container = $container;
        $this->pluginSettings = $pluginSettings;
        $this->paymentMethodsProvider = $paymentMethodsProvider;
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

        # if we do not use the limits
        # then just return everything
        if (!$settings->getUseMolliePaymentMethodLimits()) {
            return $originalData;
        }

        $cartService = $this->getCartServiceLazy();
        $cart = $cartService->getCart($context->getToken(), $context);


        $availableMolliePayments = $this->paymentMethodsProvider->getActivePaymentMethodsForAmount(
            $cart,
            $context->getCurrency()->getIsoCode(),
            [
                $context->getSalesChannel()->getId()
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