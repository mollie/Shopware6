<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Payment\MethodRemover\PaymentMethodRemoverInterface;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

#[AsDecorator(decorates: PaymentMethodRoute::class)]
final class RemovePaymentMethodRoute extends AbstractPaymentMethodRoute
{
    /**
     * @param PaymentMethodRemoverInterface[] $paymentMethodRemovers
     */
    public function __construct(private AbstractPaymentMethodRoute $decorated,
        #[AutowireIterator('mollie.method.remover')]
        private iterable $paymentMethodRemovers
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $response = $this->decorated->load($request, $context, $criteria);

        $paymentMethods = $response->getPaymentMethods();

        /** @var PaymentMethodRemoverInterface $paymentMethodRemover */
        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $paymentMethods = $paymentMethodRemover->remove($paymentMethods, $request, $context);
        }
        $response = $response->getObject();
        $response->assign(['entities' => $paymentMethods, 'elements' => $paymentMethods, 'total' => $paymentMethods->count()]);

        return new PaymentMethodRouteResponse($response);
    }
}
