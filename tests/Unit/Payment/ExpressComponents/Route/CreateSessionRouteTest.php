<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CreateSessionRoute::class)]
final class CreateSessionRouteTest extends TestCase
{
    private const ORDER_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';
    private const CUSTOMER_ID = 'f1e2d3c4b5a6f7e8d9c0b1a2f3e4d5c6';

    private FakeSessionBuilder $sessionBuilder;
    private FakeOrderSearchRepository $orderRepository;

    protected function setUp(): void
    {
        $this->sessionBuilder = new FakeSessionBuilder();
        $this->orderRepository = new FakeOrderSearchRepository();
    }

    public function testTheSessionOfTheCartIsHandedOut(): void
    {
        $cart = $this->cartWithLineItem();
        $route = $this->route(enabled: true);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext(), $cart);

        $this->assertTrue($response->isEnabled());
        $this->assertSame('ses_cart', $response->getSessionId());
        $this->assertSame('token_cart', $response->getClientAccessToken());
        $this->assertSame($cart, $this->sessionBuilder->getLastCart());
    }

    public function testNoSessionIsCreatedWhenTheComponentIsSwitchedOff(): void
    {
        $route = $this->route(enabled: false);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext(), $this->cartWithLineItem());

        $this->assertFalse($response->isEnabled());
        $this->assertSame('', $response->getSessionId());
        $this->assertFalse($this->sessionBuilder->wasCalled());
    }

    public function testTheRestrictionsAreHandedOutWithoutHoldingTheSessionBack(): void
    {
        $restrictions = VisibilityRestrictionCollection::fromArray([VisibilityRestriction::CART->value]);
        $route = $this->route(enabled: true, restrictions: $restrictions);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext(), $this->cartWithLineItem());

        $this->assertSame(['cart'], $response->getRestrictions()->toArray());
        $this->assertSame('ses_cart', $response->getSessionId());
    }

    public function testTheRestrictionsAreHandedOutEvenWhenTheComponentIsSwitchedOff(): void
    {
        $restrictions = VisibilityRestrictionCollection::fromArray([VisibilityRestriction::CART->value]);
        $route = $this->route(enabled: false, restrictions: $restrictions);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext(), $this->cartWithLineItem());

        $this->assertFalse($response->isEnabled());
        $this->assertSame(['cart'], $response->getRestrictions()->toArray());
    }

    public function testAnEmptyCartGetsNoSession(): void
    {
        $route = $this->route(enabled: true);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext(), new Cart('cart-token'));

        $this->assertTrue($response->isEnabled());
        $this->assertSame('', $response->getSessionId());
        $this->assertFalse($this->sessionBuilder->wasCalled());
    }

    public function testWithoutACartAndWithoutAnOrderThereIsNothingToPayFor(): void
    {
        $route = $this->route(enabled: true);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext());

        $this->assertSame('', $response->getSessionId());
        $this->assertFalse($this->sessionBuilder->wasCalled());
    }

    public function testAHandedInOrderIsUsedInsteadOfTheCart(): void
    {
        $order = $this->order();
        $route = $this->route(enabled: true);

        $response = $route->createSession(new Request(), new FakeSalesChannelContext(), $this->cartWithLineItem(), $order);

        $this->assertSame($order, $this->sessionBuilder->getLastOrder());
        $this->assertSame('ses_order', $response->getSessionId());
        $this->assertSame('token_order', $response->getClientAccessToken());
    }

    public function testAnOrderIdIsLoadedAndUsedInsteadOfTheCart(): void
    {
        $this->orderRepository->add($this->order());
        $route = $this->route(enabled: true);

        $response = $route->createSession($this->orderRequest(), $this->loggedInContext(), $this->cartWithLineItem());

        $this->assertSame(self::ORDER_ID, $this->sessionBuilder->getLastOrder()->getId());
        $this->assertSame('ses_order', $response->getSessionId());
    }

    public function testTheOrderOfAnotherCustomerIsNotFound(): void
    {
        $this->orderRepository->add($this->order(customerId: 'b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2'));
        $route = $this->route(enabled: true);

        $this->expectErrorCode(
            ExpressComponentsException::ORDER_NOT_FOUND,
            fn () => $route->createSession($this->orderRequest(), $this->loggedInContext())
        );
    }

    public function testTheOrderOfAnotherSalesChannelIsNotFound(): void
    {
        $this->orderRepository->add($this->order(salesChannelId: 'another-sales-channel-id'));
        $route = $this->route(enabled: true);

        $this->expectErrorCode(
            ExpressComponentsException::ORDER_NOT_FOUND,
            fn () => $route->createSession($this->orderRequest(), $this->loggedInContext())
        );
    }

    public function testAnOrderIdOfACallerWhoIsNotLoggedInIsRejected(): void
    {
        $this->orderRepository->add($this->order());
        $route = $this->route(enabled: true);

        $this->expectErrorCode(
            ExpressComponentsException::ORDER_NOT_FOUND,
            fn () => $route->createSession($this->orderRequest(), new FakeSalesChannelContext())
        );
    }

    public function testAnUnknownOrderIdIsRejected(): void
    {
        $route = $this->route(enabled: true);

        $this->expectErrorCode(
            ExpressComponentsException::ORDER_NOT_FOUND,
            fn () => $route->createSession($this->orderRequest(), $this->loggedInContext())
        );
    }

    public function testTheRouteCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->route(enabled: true)->getDecorated();
    }

    private function route(bool $enabled, ?VisibilityRestrictionCollection $restrictions = null): CreateSessionRoute
    {
        $settings = new ExpressComponentsSettings($enabled);
        if ($restrictions instanceof VisibilityRestrictionCollection) {
            $settings->setRestrictions($restrictions);
        }

        return new CreateSessionRoute(
            new FakeSettingsService(expressComponentsSettings: $settings),
            $this->sessionBuilder,
            $this->orderRepository
        );
    }

    private function orderRequest(): Request
    {
        return new Request([CreateSessionRoute::ORDER_ID_PARAMETER => self::ORDER_ID]);
    }

    private function loggedInContext(): FakeSalesChannelContext
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer(CustomerBuilder::create()->withId(self::CUSTOMER_ID)->build());

        return $context;
    }

    private function cartWithLineItem(): Cart
    {
        return CartBuilder::create()
            ->withLineItem(new LineItem('line-item-id', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->build()
        ;
    }

    private function expectErrorCode(string $errorCode, \Closure $call): void
    {
        try {
            $call();
        } catch (ExpressComponentsException $exception) {
            $this->assertSame($errorCode, $exception->getErrorCode());

            return;
        }

        $this->fail('Expected ' . $errorCode . ', but no exception was thrown.');
    }

    private function order(string $customerId = self::CUSTOMER_ID, string $salesChannelId = 'sales-channel-id'): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setSalesChannelId($salesChannelId);

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId('order-customer-id');
        $orderCustomer->setCustomerId($customerId);
        $order->setOrderCustomer($orderCustomer);

        return $order;
    }
}
