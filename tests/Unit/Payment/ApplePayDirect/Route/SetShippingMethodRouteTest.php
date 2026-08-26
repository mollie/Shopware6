<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\SetShippingMethodRoute;
use Mollie\Shopware\Unit\Fake\FakeContextSwitchRoute;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContextService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Apple Pay sheet calls this when the shopper picks a delivery option. Switching the wrong
 * thing - or answering with the old context - means the shopper is charged for a shipping method
 * they did not choose.
 */
#[CoversClass(SetShippingMethodRoute::class)]
final class SetShippingMethodRouteTest extends TestCase
{
    public function testTheChosenShippingMethodIsSwitchedInTheContext(): void
    {
        $contextSwitchRoute = new FakeContextSwitchRoute();

        $this->route($contextSwitchRoute)->setShipping($this->request('shipping-method-1'), new FakeSalesChannelContext());

        $this->assertSame(
            'shipping-method-1',
            $contextSwitchRoute->getLastSwitch()[SalesChannelContextService::SHIPPING_METHOD_ID]
        );
    }

    /**
     * The shipping costs are recalculated in the new context, so the caller has to get that one
     * back and not the context it came in with.
     */
    public function testTheRecalculatedContextIsHandedBack(): void
    {
        $newContext = new FakeSalesChannelContext('sc-1', 'switched-token');

        $response = $this->route(contextService: new FakeSalesChannelContextService($newContext))
            ->setShipping($this->request('shipping-method-1'), new FakeSalesChannelContext())
        ;

        $this->assertSame($newContext, $response->getSalesChannelContext());
    }

    /**
     * A request without an identifier would silently leave the previous method selected, so the
     * caller has to hear about it instead.
     */
    public function testARequestWithoutAShippingMethodIsRejected(): void
    {
        $this->expectException(ApplePayDirectException::class);

        $this->route()->setShipping(new Request(), new FakeSalesChannelContext());
    }

    public function testARejectedRequestSwitchesNothing(): void
    {
        $contextSwitchRoute = new FakeContextSwitchRoute();

        try {
            $this->route($contextSwitchRoute)->setShipping(new Request(), new FakeSalesChannelContext());
        } catch (ApplePayDirectException) {
            // the assertion below is what this test is about
        }

        $this->assertSame([], $contextSwitchRoute->getSwitches());
    }

    private function request(string $shippingMethodId): Request
    {
        return new Request([], ['identifier' => $shippingMethodId]);
    }

    private function route(
        ?FakeContextSwitchRoute $contextSwitchRoute = null,
        ?FakeSalesChannelContextService $contextService = null,
    ): SetShippingMethodRoute {
        return new SetShippingMethodRoute(
            $contextSwitchRoute ?? new FakeContextSwitchRoute(),
            $contextService ?? new FakeSalesChannelContextService(new FakeSalesChannelContext()),
            new NullLogger()
        );
    }
}
