<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Mollie\Shopware\Component\PaymentLink\Controller\PaymentLinkController;
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
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDecorator(decorates: PaymentMethodRoute::class)]
final class RemovePaymentMethodRoute extends AbstractPaymentMethodRoute
{
    /**
     * @param AbstractPaymentRemover[] $paymentMethodRemovers
     */
    public function __construct(private AbstractPaymentMethodRoute $decorated,
        #[AutowireIterator('mollie.method.remover')]
        private iterable $paymentMethodRemovers,
        private RequestStack $requestStack
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $response = $this->decorated->load($request, $context, $criteria);

        // The $request handed to this route is a throwaway empty Request created by the storefront
        // page loaders (GenericPageLoader/CheckoutConfirmPageLoader/AccountEditOrderPageLoader via
        // RouteRequestEvent), so it never carries _controller/_route/orderId. We therefore read the
        // page context from the request stack instead. The payment-method route is loaded on every
        // page (footer), so we only run our removers on the checkout and edit-order pages.
        $pageRequest = $this->findPageRequest();

        if ($pageRequest === null) {
            return $response;
        }

        $orderId = (string) $pageRequest->get('orderId', '');

        $paymentMethods = $response->getPaymentMethods();

        /** @var AbstractPaymentRemover $paymentMethodRemover */
        foreach ($this->paymentMethodRemovers as $paymentMethodRemover) {
            $paymentMethods = $paymentMethodRemover->remove($paymentMethods, $orderId, $context);
        }
        $responseObject = $response->getObject();
        $responseObject->assign(['entities' => $paymentMethods, 'elements' => $paymentMethods, 'total' => $paymentMethods->count()]);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult<\Shopware\Core\Checkout\Payment\PaymentMethodCollection> $responseObject */
        return new PaymentMethodRouteResponse($responseObject);
    }

    /**
     * Our own controllers forward to the checkout pages instead of redirecting (e.g. PayPal Express
     * finish renders frontend.checkout.confirm.page). A forward is rendered in a sub request, so the
     * main request still points at the Mollie controller. The current request therefore has to be
     * checked first, the main request is only the fallback.
     */
    private function findPageRequest(): ?Request
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest !== null && $this->shouldRemove($currentRequest)) {
            return $currentRequest;
        }

        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest !== null && $this->shouldRemove($mainRequest)) {
            return $mainRequest;
        }

        return null;
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
                PaymentLinkController::class,
            ]
        );
    }
}
