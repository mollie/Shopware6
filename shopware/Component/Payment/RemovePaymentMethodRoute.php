<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Payment\MethodRemover\AbstractPaymentRemover;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Controller\CheckoutController;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

#[AsDecorator(decorates: PaymentMethodRoute::class)]
final class RemovePaymentMethodRoute extends AbstractPaymentMethodRoute
{
    /**
     * @param AbstractPaymentRemover[] $paymentMethodRemovers
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

        if ($this->shouldRemove($request) === false) {
            return $response;
        }

        $orderId = $request->get('orderId','');

        $paymentMethods = $response->getPaymentMethods();

        /** @var AbstractPaymentRemover $paymentMethodRemover */
        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $paymentMethods = $paymentMethodRemover->remove($paymentMethods, $orderId, $context);
        }
        $response = $response->getObject();
        $response->assign(['entities' => $paymentMethods, 'elements' => $paymentMethods, 'total' => $paymentMethods->count()]);

        return new PaymentMethodRouteResponse($response);
    }

    private function getControllerClass(Request $request): ?string
    {
        $controller = $request->attributes->get('_controller');
        if ($controller === null) {
            return null;
        }
        $controllerParts = explode('::', $controller);

        $controllerClass = $controllerParts[0] ?? null;
        if ($controllerClass === null) {
            return null;
        }

        return $controllerClass;
    }

    private function shouldRemove(Request $request): bool
    {
        $controllerClass = $this->getControllerClass($request);
        if ($controllerClass === null) {
            return false;
        }

        return in_array(
            $controllerClass,
            [
                CheckoutController::class,
                AccountOrderController::class,
            ]
        );
    }
}
