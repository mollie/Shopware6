<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FailureMode;

use Mollie\Shopware\Component\FailureMode\FailureModeOrderController;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\FailureMode\Fake\FakeAccountEditOrderPageLoader;
use Mollie\Shopware\Unit\FailureMode\Fake\FakeAccountOrderController;
use Mollie\Shopware\Unit\FailureMode\Fake\FakeOrderRoute;
use Mollie\Shopware\Unit\Fake\FakeRouter;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * A failed payment normally lands the customer on Shopware's edit-order page. With the plugin's own
 * failure mode they get the Mollie retry page instead - unless the merchant switched Shopware's
 * behaviour back on, in which case nothing here may interfere.
 */
#[CoversClass(FailureModeOrderController::class)]
final class FailureModeOrderControllerTest extends TestCase
{
    private const ORDER_ID = 'order-1';

    private FakeRouter $router;

    public function testTheShopwarePageIsShownWhenTheMerchantChoseShopwaresOwnFailureHandling(): void
    {
        $decorated = new FakeAccountOrderController();

        $response = $this->controller($decorated, shopwareFailedPayment: true)
            ->editOrder(self::ORDER_ID, new Request(), new FakeSalesChannelContext())
        ;

        $this->assertSame(['editOrder'], $decorated->getCalls());
        $this->assertSame('editOrder', $response->getContent());
    }

    /**
     * An order that can no longer be edited (cancelled, already paid) has no retry page to show.
     * The customer is sent back to their order list with the reason instead of onto an error page.
     */
    public function testAnOrderThatCanNoLongerBeEditedSendsTheCustomerBackToTheirOrders(): void
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setOrderNumber('10000');

        $response = $this->controller(
            new FakeAccountOrderController(),
            pageLoader: new FakeAccountEditOrderPageLoader(failure: OrderException::orderNotFound(self::ORDER_ID)),
            orderRoute: new FakeOrderRoute($order)
        )->editOrder(self::ORDER_ID, new Request(), new FakeSalesChannelContext());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('frontend.account.order.page', $this->router->getLastRouteName());
    }

    /**
     * Without the order there is no order number for the message, but the customer still has to be
     * sent somewhere sensible.
     */
    public function testAnOrderThatIsGoneStillSendsTheCustomerBackToTheirOrders(): void
    {
        $response = $this->controller(
            new FakeAccountOrderController(),
            pageLoader: new FakeAccountEditOrderPageLoader(failure: OrderException::orderNotFound(self::ORDER_ID)),
            orderRoute: new FakeOrderRoute()
        )->editOrder(self::ORDER_ID, new Request(), new FakeSalesChannelContext());

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * @param \Closure(FailureModeOrderController, Request, FakeSalesChannelContext): Response $call
     */
    #[DataProvider('passedThroughCallProvider')]
    public function testEverythingButTheEditOrderPageIsHandedToShopware(\Closure $call, string $expectedMethod): void
    {
        $decorated = new FakeAccountOrderController();

        $response = $call($this->controller($decorated), new Request(), new FakeSalesChannelContext());

        $this->assertSame([$expectedMethod], $decorated->getCalls());
        $this->assertSame($expectedMethod, $response->getContent());
    }

    /**
     * Every one of these is Shopware's business; the decorator only exists for the edit-order page.
     *
     * @return array<string, array{\Closure(FailureModeOrderController, Request, FakeSalesChannelContext): Response, string}>
     */
    public static function passedThroughCallProvider(): array
    {
        return [
            'the order list' => [fn (FailureModeOrderController $c, Request $r, FakeSalesChannelContext $s) => $c->orderOverview($r, $s), 'orderOverview'],
            'cancelling an order' => [fn (FailureModeOrderController $c, Request $r, FakeSalesChannelContext $s) => $c->cancelOrder($r, $s), 'cancelOrder'],
            'a single order' => [fn (FailureModeOrderController $c, Request $r, FakeSalesChannelContext $s) => $c->orderSingleOverview($r, $s), 'orderSingleOverview'],
            'the order detail box' => [fn (FailureModeOrderController $c, Request $r, FakeSalesChannelContext $s) => $c->ajaxOrderDetail($r, $s), 'ajaxOrderDetail'],
            'changing the payment method' => [fn (FailureModeOrderController $c, Request $r, FakeSalesChannelContext $s) => $c->orderChangePayment(self::ORDER_ID, $r, $s), 'orderChangePayment'],
            'updating the order' => [fn (FailureModeOrderController $c, Request $r, FakeSalesChannelContext $s) => $c->updateOrder(self::ORDER_ID, $r, $s), 'updateOrder'],
        ];
    }

    private function controller(
        FakeAccountOrderController $decorated,
        bool $shopwareFailedPayment = false,
        ?FakeAccountEditOrderPageLoader $pageLoader = null,
        ?FakeOrderRoute $orderRoute = null,
    ): FailureModeOrderController {
        $this->router = new FakeRouter('https://shop.test/account/order');

        $controller = new FailureModeOrderController(
            $decorated,
            $orderRoute ?? new FakeOrderRoute(),
            new FakeSettingsService(paymentSettings: PaymentSettings::createFromShopwareArray([
                PaymentSettings::KEY_SHOPWARE_FAILED_PAYMENT => $shopwareFailedPayment,
            ])),
            $pageLoader ?? new FakeAccountEditOrderPageLoader(new PaymentMethodCollection())
        );

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $container = new Container();
        $container->set('router', $this->router);
        $container->set('event_dispatcher', new EventDispatcher());
        $container->set('request_stack', $requestStack);
        $container->set('translator', new IdentityTranslator());

        $controller->setContainer($container);

        return $controller;
    }
}
