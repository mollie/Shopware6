<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute;

use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class RemovePaymentMethodRoute63 extends AbstractPaymentMethodRoute
{
    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var PaymentMethodRemoverInterface[]
     */
    private $paymentMethodRemovers;

    /**
     * @param AbstractPaymentMethodRoute                  $corePaymentMethodRoute
     * @param \Traversable<PaymentMethodRemoverInterface> $paymentMethodRemovers
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, \Traversable $paymentMethodRemovers)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->paymentMethodRemovers = iterator_to_array($paymentMethodRemovers);
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
     */
    public function load(Request $request, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context);

        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $originalData = $paymentMethodRemover->removePaymentMethods($originalData, $context);
        }

        return $originalData;
    }
}
