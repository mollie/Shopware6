<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PayPalExpress;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Payment\PayPalExpress\PayPalExpressStoreFrontSubscriber;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * The storefront only renders the PayPal express button from these twig parameters. A missing one
 * means the button is silently gone from the shop.
 */
#[CoversClass(PayPalExpressStoreFrontSubscriber::class)]
final class PayPalExpressStoreFrontSubscriberTest extends TestCase
{
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new FakeLogger();
    }

    public function testTheButtonSettingsReachTheStorefront(): void
    {
        $settings = new PayPalExpressSettings(true);
        $settings->setStyle(2);
        $settings->setShape(1);
        $settings->setRestrictions(new VisibilityRestrictionCollection([VisibilityRestriction::CART]));
        $event = $this->renderEvent();

        $this->subscriber($settings, 'paypal-express-method-id')->onStorefrontRender($event);

        $this->assertTrue($event->getParameter('mollie_paypalexpress_enabled'));
        $this->assertSame(2, $event->getParameter('mollie_paypalexpress_style'));
        $this->assertSame(1, $event->getParameter('mollie_paypalexpress_shape'));
        $this->assertSame([VisibilityRestriction::CART->value], $event->getParameter('mollie_paypalexpress_restrictions'));
    }

    public function testDisabledPayPalExpressIsReportedToTheStorefront(): void
    {
        $event = $this->renderEvent();

        $this->subscriber(new PayPalExpressSettings(false), 'paypal-express-method-id')->onStorefrontRender($event);

        $this->assertFalse($event->getParameter('mollie_paypalexpress_enabled'));
    }

    /**
     * Without the payment method there is nothing to render, and the storefront falls back to its
     * own default instead of a half filled parameter set.
     */
    public function testNoParametersAreSetWhenThePaymentMethodIsNotInstalled(): void
    {
        $event = $this->renderEvent();

        $this->subscriber(new PayPalExpressSettings(true), null)->onStorefrontRender($event);

        $this->assertNull($event->getParameter('mollie_paypalexpress_enabled'));
    }

    /**
     * A failure here must not take down the whole page, so it is logged and the render continues.
     */
    public function testAFailingPaymentMethodLookupIsLoggedInsteadOfBreakingThePage(): void
    {
        $paymentMethodRepository = new FakePaymentMethodRepository('paypal-express-method-id');
        $paymentMethodRepository->withLookupFailure(new \RuntimeException('the payment method table is unreachable'));
        $event = $this->renderEvent();

        $subscriber = new PayPalExpressStoreFrontSubscriber(
            $paymentMethodRepository,
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            $this->logger
        );

        $subscriber->onStorefrontRender($event);

        $this->assertTrue($this->logger->hasRecordThatContains(LogLevel::ERROR, 'Failed to assign paypal express data to storefront'));
        $this->assertNull($event->getParameter('mollie_paypalexpress_enabled'));
    }

    public function testTheSubscriberListensToTheStorefrontRender(): void
    {
        $this->assertArrayHasKey(StorefrontRenderEvent::class, PayPalExpressStoreFrontSubscriber::getSubscribedEvents());
    }

    private function subscriber(PayPalExpressSettings $settings, ?string $paymentMethodId): PayPalExpressStoreFrontSubscriber
    {
        return new PayPalExpressStoreFrontSubscriber(
            new FakePaymentMethodRepository($paymentMethodId),
            new FakeSettingsService(paypalExpressSettings: $settings),
            $this->logger
        );
    }

    private function renderEvent(): StorefrontRenderEvent
    {
        return new StorefrontRenderEvent('view.html.twig', [], new Request(), new FakeSalesChannelContext());
    }
}
