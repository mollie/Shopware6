<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Subscription;

use Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover\PaymentMethodRemover;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProviderInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPaymentMethodRoute63 extends AbstractPaymentMethodRoute
{
    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var PaymentMethodRemover
     */
    private $subscriptionRemover;


    /**
     * @param AbstractPaymentMethodRoute $corePaymentMethodRoute
     * @param Container $container
     * @param SettingsService $pluginSettings
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, Container $container, SettingsService $pluginSettings)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;

        $this->subscriptionRemover = new PaymentMethodRemover(
            $container,
            $pluginSettings
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
     * @throws \Exception
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context);

        return $this->subscriptionRemover->removePaymentMethods($originalData, $context);
    }

}
