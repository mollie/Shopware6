<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute;

use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class RemovePaymentMethodRoute64 extends AbstractPaymentMethodRoute
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
     * @param \Traversable<PaymentMethodRemoverInterface> $paymentMethodRemovers
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, \Traversable $paymentMethodRemovers)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->paymentMethodRemovers = iterator_to_array($paymentMethodRemovers);
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->corePaymentMethodRoute;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $originalData = $this->corePaymentMethodRoute->load($request, $context, $criteria);

        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $originalData = $paymentMethodRemover->removePaymentMethods($originalData, $context);
        }

        return $originalData;
    }
}
