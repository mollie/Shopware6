<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\MollieLimits;

use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;


class MollieLimitsPaymentMethodRoute64 extends AbstractPaymentMethodRoute
{

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var PaymentMethodRemoverInterface
     */
    private $mollieLimits;

    /**
     * @param AbstractPaymentMethodRoute    $corePaymentMethodRoute
     * @param PaymentMethodRemoverInterface $mollieLimits
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, PaymentMethodRemoverInterface $mollieLimits)
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
     * @param Request             $request
     * @param SalesChannelContext $context
     * @param Criteria            $criteria
     * @return PaymentMethodRouteResponse
     * @throws \Exception
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context, $criteria);

        return $this->mollieLimits->removePaymentMethods($originalData, $context);
    }

}
