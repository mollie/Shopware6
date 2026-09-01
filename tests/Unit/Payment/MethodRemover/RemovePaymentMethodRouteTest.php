<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover;

use Mollie\Shopware\Component\Payment\MethodRemover\RemovePaymentMethodRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\PayPalExpressController;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\MethodRemover\Fake\FakePaymentMethodRoute;
use Mollie\Shopware\Unit\Payment\MethodRemover\Fake\FakePaymentRemover;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Controller\NavigationController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(RemovePaymentMethodRoute::class)]
final class RemovePaymentMethodRouteTest extends TestCase
{
    public function testRemoversRunOnCheckoutPage(): void
    {
        $remover = new FakePaymentRemover(['other-id']);
        $requestStack = $this->buildRequestStack($this->buildRequest(CheckoutController::class));

        $result = $this->load($remover, $requestStack);

        $this->assertTrue($remover->called);
        $this->assertCount(1, $result->getPaymentMethods());
        $this->assertNull($result->getPaymentMethods()->get('other-id'));
    }

    public function testRemoversAreSkippedOnOtherPages(): void
    {
        $remover = new FakePaymentRemover(['other-id']);
        $requestStack = $this->buildRequestStack($this->buildRequest(NavigationController::class));

        $result = $this->load($remover, $requestStack);

        $this->assertFalse($remover->called);
        $this->assertCount(2, $result->getPaymentMethods());
    }

    /**
     * PayPal Express finishes the checkout by forwarding to the confirm page. The main request still
     * points at our own controller, the confirm page is rendered in the sub request.
     */
    public function testRemoversRunWhenCheckoutPageIsRenderedInAForward(): void
    {
        $remover = new FakePaymentRemover(['other-id']);
        $requestStack = $this->buildRequestStack(
            $this->buildRequest(PayPalExpressController::class, 'finishCheckout'),
            $this->buildRequest(CheckoutController::class)
        );

        $result = $this->load($remover, $requestStack);

        $this->assertTrue($remover->called);
        $this->assertCount(1, $result->getPaymentMethods());
        $this->assertNull($result->getPaymentMethods()->get('other-id'));
    }

    public function testOrderIdIsReadFromTheRenderedPage(): void
    {
        $remover = new FakePaymentRemover();
        $mainRequest = $this->buildRequest(PayPalExpressController::class, 'finishCheckout');
        $subRequest = $this->buildRequest(CheckoutController::class);
        $subRequest->attributes->set('orderId', 'order-id');

        $this->load($remover, $this->buildRequestStack($mainRequest, $subRequest));

        $this->assertSame('order-id', $remover->receivedOrderId);
    }

    public function testRemoversAreSkippedWithoutRequest(): void
    {
        $remover = new FakePaymentRemover(['other-id']);

        $result = $this->load($remover, new RequestStack());

        $this->assertFalse($remover->called);
        $this->assertCount(2, $result->getPaymentMethods());
    }

    private function load(FakePaymentRemover $remover, RequestStack $requestStack): \Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse
    {
        $route = new RemovePaymentMethodRoute(
            new FakePaymentMethodRoute($this->buildPaymentMethods()),
            [$remover],
            $requestStack
        );

        return $route->load(new Request(), new FakeSalesChannelContext(), new Criteria());
    }

    private function buildRequestStack(Request ...$requests): RequestStack
    {
        $requestStack = new RequestStack();

        foreach ($requests as $request) {
            $requestStack->push($request);
        }

        return $requestStack;
    }

    private function buildRequest(string $controllerClass, string $action = 'index'): Request
    {
        $request = new Request();
        $request->attributes->set('_controller', $controllerClass . '::' . $action);

        return $request;
    }

    private function buildPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection([
            PaymentMethodBuilder::create()->withId('kept-id')->build(),
            PaymentMethodBuilder::create()->withId('other-id')->build(),
        ]);
    }
}
