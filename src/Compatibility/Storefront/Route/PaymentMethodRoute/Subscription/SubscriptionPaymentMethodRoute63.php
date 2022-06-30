<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Subscription;

use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPaymentMethodRoute63 extends AbstractPaymentMethodRoute
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
     * @param AbstractPaymentMethodRoute    $corePaymentMethodRoute
     * @param PaymentMethodRemoverInterface $paymentMethodRemover
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
     * @return PaymentMethodRouteResponse
     * @throws \Exception
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context);

        return $this->paymentMethodRemover->removePaymentMethods($originalData, $context);
    }

}
