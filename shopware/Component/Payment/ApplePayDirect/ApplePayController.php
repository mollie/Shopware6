<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractCreateSessionRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractGetApplePayIdRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractGetCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractGetShippingMethodsRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractPayRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractRestoreCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractSetShippingMethodRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetApplePayIdRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetShippingMethodsRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\PayRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\RestoreCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\SetShippingMethodRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class ApplePayController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: CreateSessionRoute::class)]
        private AbstractCreateSessionRoute $createSessionRoute,
        #[Autowire(service: GetCartRoute::class)]
        private AbstractGetCartRoute $getCartRoute,
        #[Autowire(service: GetShippingMethodsRoute::class)]
        private AbstractGetShippingMethodsRoute $getShippingMethodsRoute,
        #[Autowire(service: SetShippingMethodRoute::class)]
        private AbstractSetShippingMethodRoute $setShippingMethodRoute,
        #[Autowire(service: PayRoute::class)]
        private AbstractPayRoute $payRoute,
        #[Autowire(service: GetApplePayIdRoute::class)]
        private AbstractGetApplePayIdRoute $getApplePayIdRoute,
        #[Autowire(service: ApplePayDirectEnabledRoute::class)]
        private AbstractApplePayDirectEnabledRoute $applePayDirectEnabledRoute,
        #[Autowire(service: RestoreCartRoute::class)]
        private AbstractRestoreCartRoute $restoreCartRoute,
    ) {
    }

    #[Route(name: 'frontend.mollie.apple-pay.validate', path: '/mollie/apple-pay/validate', methods: ['POST'], options: ['seo' => false])]
    public function createSession(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $session = '';
        $success = false;
        try {
            $response = $this->createSessionRoute->session($request, $salesChannelContext);
            $success = true;
            $session = $response->getSession();
        } catch (\Throwable $exception) {
        }

        return new JsonResponse([
            'data' => [
                'success' => $success,
                'session' => json_encode($session),
            ]
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.shipping-methods', path: '/mollie/apple-pay/shipping-methods', methods: ['POST'], options: ['seo' => false])]
    public function updateShippingMethods(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $success = false;
        $cart = [];
        $shippingMethods = [];
        $error = '';
        try {
            $cartResponse = $this->getCartRoute->cart($request, $salesChannelContext);
            $shippingMethodsResponse = $this->getShippingMethodsRoute->methods($request, $salesChannelContext);

            $cart = $cartResponse->getCart();
            $shippingMethods = $shippingMethodsResponse->getShippingMethods();
            $success = true;
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'shippingmethods' => $shippingMethods,
            'data' => [
                'cart' => $cart,
                'error' => $error,
                'success' => $success,
            ]
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.set-shipping', path: '/mollie/apple-pay/set-shipping', methods: ['POST'], options: ['seo' => false])]
    public function setShippingMethod(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $cart = [];
        $success = false;
        $error = '';
        try {
            $setShippingMethodResponse = $this->setShippingMethodRoute->setShipping($request, $salesChannelContext);
            $salesChannelContext = $setShippingMethodResponse->getSalesChannelContext();
            $cartResponse = $this->getCartRoute->cart($request, $salesChannelContext);
            $cart = $cartResponse->getCart();
            $success = true;
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        return new JsonResponse([
            'data' => [
                'success' => $success,
                'cart' => $cart,
                'error' => $error,
            ]
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.start-payment', path: '/mollie/apple-pay/start-payment', methods: ['POST'], options: ['seo' => false])]
    public function startPayment(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $errorSnippet = 'molliePayments.payments.applePayDirect.paymentError';
        try {
            $response = $this->payRoute->pay($request, $salesChannelContext);
            $orderId = $response->getOrderId();
            $finishUrl = $this->generateUrl('frontend.checkout.finish.page', ['orderId' => $orderId]);

            return new RedirectResponse($finishUrl);
        } catch (ApplePayDirectException $exception) {
            $this->addFlash('danger', $this->trans($errorSnippet));
            if ($exception->getErrorCode() === ApplePayDirectException::PAYMENT_FAILED) {
                $orderId = $exception->getParameter('orderId');

                return $this->forwardToRoute('frontend.account.edit-order.page', [], ['orderId' => $orderId]);
            }

            return $this->forwardToRoute('frontend.checkout.confirm.page');
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->forwardToRoute('frontend.checkout.confirm.page');
        }
    }

    #[Route(name: 'frontend.mollie.apple-pay.id', path: '/mollie/apple-pay/applepay-id', methods: ['GET'], options: ['seo' => false])]
    public function getApplePayId(SalesChannelContext $salesChannelContext): Response
    {
        $id = 'not-found';
        try {
            $response = $this->getApplePayIdRoute->getId($salesChannelContext);

            $id = $response->getId() ?? $id;
        } catch (\Throwable $exception) {
        }

        return new JsonResponse([
            'id' => $id,
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.available', path: '/mollie/apple-pay/available', methods: ['GET'], options: ['seo' => false])]
    public function isDirectAvailable(SalesChannelContext $salesChannelContext): Response
    {
        $available = false;
        try {
            $response = $this->applePayDirectEnabledRoute->getEnabled($salesChannelContext);
            $available = $response->isEnabled();
        } catch (\Throwable $exception) {
        }

        return new JsonResponse([
            'available' => $available,
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.restore-cart', path: '/mollie/apple-pay/restore-cart', methods: ['POST'], options: ['seo' => false])]
    public function restoreCart(SalesChannelContext $salesChannelContext): Response
    {
        $success = false;
        try {
            $response = $this->restoreCartRoute->restore($salesChannelContext);
            $success = $response->isSuccessful();
        } catch (\Throwable $exception) {
        }

        return new JsonResponse([
            'success' => $success,
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.add-product', path: '/mollie/apple-pay/add-product', methods: ['POST'], options: ['seo' => false])]
    public function addProduct(SalesChannelContext $salesChannelContext): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
        ]);
    }
}
