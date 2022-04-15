<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits;

use Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits\Service\MollieLimitsRemover;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
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
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, MollieLimitsRemover $mollieLimits)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->mollieLimits = $mollieLimits;
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
