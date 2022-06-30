<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Subscription;

use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPaymentMethodRoute64 extends AbstractPaymentMethodRoute
{

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var PaymentMethodRemoverInterface
     */
    private $paymentMethodRemover;

    /**
     * @param AbstractPaymentMethodRoute $corePaymentMethodRoute
     * @param ContainerInterface         $container
     * @param SettingsService            $pluginSettings
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, PaymentMethodRemoverInterface $paymentMethodRemover)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->paymentMethodRemover = $paymentMethodRemover;
    }


    /**
     * @return AbstractPaymentMethodRoute
     */
    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->corePaymentMethodRoute;
    }

    /**
     * @param Request             $request
     * @param SalesChannelContext $context
     * @param Criteria            $criteria
     * @return PaymentMethodRouteResponse
     * @throws \Exception
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context, $criteria);

        $newData = $this->paymentMethodRemover->removePaymentMethods($originalData, $context);
//        dd('stop', $newData);
        return $this->paymentMethodRemover->removePaymentMethods($originalData, $context);
    }

}
