<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits;

use Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits\Service\MollieLimitsRemover;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;


class MollieLimitsPaymentMethodRoute64 extends AbstractPaymentMethodRoute
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
     * @param Criteria $criteria
     * @return PaymentMethodRouteResponse
     * @throws \Exception
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context, $criteria);
        $newData = $this->mollieLimits->removePaymentMethods($originalData, $context);
        
        if (count($newData->getPaymentMethods()) == 0) {
            return $this->corePaymentMethodRoute->load($request, $context, $criteria);
        }
        return $newData;
    }

}
