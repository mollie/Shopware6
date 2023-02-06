<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute;

use Kiener\MolliePayments\Service\ContextState\ContextStateHandler;
use Kiener\MolliePayments\Service\Payment\Remover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class RemovePaymentMethodRoute64 extends AbstractPaymentMethodRoute
{

    /**
     * @var ContextStateHandler
     */
    private $contextState;

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $corePaymentMethodRoute;

    /**
     * @var PaymentMethodRemoverInterface[]
     */
    private $paymentMethodRemovers;

    /**
     * @param AbstractPaymentMethodRoute $corePaymentMethodRoute
     * @param \Traversable<PaymentMethodRemoverInterface> $paymentMethodRemovers
     */
    public function __construct(AbstractPaymentMethodRoute $corePaymentMethodRoute, \Traversable $paymentMethodRemovers)
    {
        $this->corePaymentMethodRoute = $corePaymentMethodRoute;
        $this->paymentMethodRemovers = iterator_to_array($paymentMethodRemovers);

        $this->contextState = new ContextStateHandler('payment_method_remover');
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
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        # sometimes it can happen that an infinite-loop occurs due to the
        # loading of the cartService data below. So we only do this once in here!
        if ($this->contextState->hasSnapshot($context)) {
            $cachedData = $this->contextState->getSnapshot($context);

            if ($cachedData instanceof PaymentMethodRouteResponse) {
                return $cachedData;
            }
        }

        $originalData = $this->corePaymentMethodRoute->load($request, $context, $criteria);

        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $originalData = $paymentMethodRemover->removePaymentMethods($originalData, $context);
        }

        # save our data as snapshot
        $this->contextState->saveSnapshot($originalData, $context);

        return $originalData;
    }
}
