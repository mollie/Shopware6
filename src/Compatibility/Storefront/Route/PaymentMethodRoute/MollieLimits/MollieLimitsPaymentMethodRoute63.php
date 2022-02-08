<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits;

use Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits\Service\MollieLimitsRemover;
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


class MollieLimitsPaymentMethodRoute63 extends AbstractPaymentMethodRoute
{

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var MollieLimitsRemover
     */
    private $mollieLimits;


    /**
     * @param AbstractPaymentMethodRoute $corePaymentMethodRoute
     * @param Container $container
     * @param SettingsService $pluginSettings
     * @param ActivePaymentMethodsProviderInterface $paymentMethodsProvider
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, Container $container, SettingsService $pluginSettings, ActivePaymentMethodsProviderInterface $paymentMethodsProvider)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;

        $this->mollieLimits = new MollieLimitsRemover(
            $container,
            $pluginSettings,
            $paymentMethodsProvider
        );
    }


    /**
     * @return AbstractPaymentMethodRoute
     */
    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->corePaymentMethodRoute;
    }


    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @return PaymentMethodRouteResponse
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context, $criteria);

        return $this->mollieLimits->removePaymentMethods($originalData, $context);
    }

}