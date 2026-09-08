<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Subscriber;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsData;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Subscriber\ExpressComponentsCartSubscriber;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ExpressComponentsCartSubscriber::class)]
final class ExpressComponentsCartSubscriberTest extends TestCase
{
    private FakeSessionBuilder $sessionBuilder;

    protected function setUp(): void
    {
        $this->sessionBuilder = new FakeSessionBuilder();
    }

    public function testTheCartPageGetsTheTokenTheComponentIsMountedWith(): void
    {
        $page = $this->cartPage();
        $subscriber = $this->subscriber(enabled: true);

        $subscriber->onCartPageLoaded(new CheckoutCartPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertSame('token_cart', $this->expressComponentsData($page)->getClientAccessToken());
    }

    public function testAPageTheMerchantSwitchedTheComponentOffForGetsNoSession(): void
    {
        $page = $this->cartPage();
        $restrictions = VisibilityRestrictionCollection::fromArray([VisibilityRestriction::CART->value]);
        $subscriber = $this->subscriber(enabled: true, restrictions: $restrictions);

        $subscriber->onCartPageLoaded(new CheckoutCartPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertFalse($this->sessionBuilder->wasCalled());
        $this->assertSame('', $this->expressComponentsData($page)->getClientAccessToken());
        $this->assertSame(['cart'], $this->expressComponentsData($page)->getRestrictions());
    }

    public function testNothingIsAssignedWhenTheComponentIsSwitchedOff(): void
    {
        $page = $this->cartPage();
        $subscriber = $this->subscriber(enabled: false);

        $subscriber->onCartPageLoaded(new CheckoutCartPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertNull($page->getExtension(ExpressComponentsData::EXTENSION));
        $this->assertFalse($this->sessionBuilder->wasCalled());
    }

    public function testTheEditOrderPageBuildsTheSessionFromItsOrder(): void
    {
        $order = new OrderEntity();
        $order->setId('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6');

        $page = new AccountEditOrderPage();
        $page->setOrder($order);

        $subscriber = $this->subscriber(enabled: true);

        $subscriber->onEditOrderPageLoaded(new AccountEditOrderPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertSame($order, $this->sessionBuilder->getLastOrder());
        $this->assertSame('token_order', $this->expressComponentsData($page)->getClientAccessToken());
    }

    private function subscriber(bool $enabled, ?VisibilityRestrictionCollection $restrictions = null): ExpressComponentsCartSubscriber
    {
        $settings = new ExpressComponentsSettings($enabled);
        if ($restrictions instanceof VisibilityRestrictionCollection) {
            $settings->setRestrictions($restrictions);
        }

        $route = new CreateSessionRoute(
            new FakeSettingsService(expressComponentsSettings: $settings),
            $this->sessionBuilder,
            new FakeOrderSearchRepository()
        );

        return new ExpressComponentsCartSubscriber(
            new FakeSettingsService(expressComponentsSettings: $settings),
            $route,
            new FakeLogger()
        );
    }

    private function cartPage(): CheckoutCartPage
    {
        $page = new CheckoutCartPage();
        $page->setCart($this->cartWithLineItem());

        return $page;
    }

    private function cartWithLineItem(): Cart
    {
        return CartBuilder::create()
            ->withLineItem(new LineItem('line-item-id', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->build()
        ;
    }

    private function expressComponentsData(Struct $page): ExpressComponentsData
    {
        $data = $page->getExtension(ExpressComponentsData::EXTENSION);
        if (! $data instanceof ExpressComponentsData) {
            $this->fail('The page carries no express components data.');
        }

        return $data;
    }
}
