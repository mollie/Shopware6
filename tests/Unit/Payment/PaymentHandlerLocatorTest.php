<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeSubscriptionAwarePaymentHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The locator is what maps a Mollie method name back onto its handler - the payment link and the
 * subscription renewal both depend on finding the right one.
 */
#[CoversClass(PaymentHandlerLocator::class)]
final class PaymentHandlerLocatorTest extends TestCase
{
    public function testEveryRegisteredHandlerIsKept(): void
    {
        $locator = new PaymentHandlerLocator([
            new FakePaymentMethodHandler(PaymentMethod::PAYPAL),
            new FakePaymentMethodHandler(PaymentMethod::CREDIT_CARD),
        ]);

        $this->assertCount(2, $locator->getPaymentMethods());
    }

    public function testAHandlerIsFoundByItsMollieMethodName(): void
    {
        $creditCard = new FakePaymentMethodHandler(PaymentMethod::CREDIT_CARD);
        $locator = new PaymentHandlerLocator([new FakePaymentMethodHandler(PaymentMethod::PAYPAL), $creditCard]);

        $this->assertSame($creditCard, $locator->findByPaymentMethod('creditcard'));
    }

    public function testAnUnknownMethodNameFindsNoHandler(): void
    {
        $locator = new PaymentHandlerLocator([new FakePaymentMethodHandler(PaymentMethod::PAYPAL)]);

        $this->assertNull($locator->findByPaymentMethod('ideal'));
    }

    /**
     * Shopware stores the handler identifier on the payment method, so the locator has to resolve
     * a handler by its class name too.
     */
    public function testAHandlerIsFoundByItsClassName(): void
    {
        $handler = new FakePaymentMethodHandler(PaymentMethod::PAYPAL);
        $locator = new PaymentHandlerLocator([$handler]);

        $this->assertSame($handler, $locator->findByIdentifier(FakePaymentMethodHandler::class));
    }

    public function testAnUnknownIdentifierFindsNoHandler(): void
    {
        $locator = new PaymentHandlerLocator([new FakePaymentMethodHandler(PaymentMethod::PAYPAL)]);

        $this->assertNull($locator->findByIdentifier('Some\Other\Handler'));
    }

    /**
     * A subscription may only be renewed with a method that supports a recurring mandate, which is
     * exactly what the marker interface says.
     */
    public function testOnlySubscriptionAwareHandlersAreReportedAsSubscriptionMethods(): void
    {
        $subscriptionHandler = new FakeSubscriptionAwarePaymentHandler();
        $locator = new PaymentHandlerLocator([
            new FakePaymentMethodHandler(PaymentMethod::PAYPAL),
            $subscriptionHandler,
        ]);

        $this->assertSame([$subscriptionHandler], $locator->getSubscriptionMethods());
    }

    public function testWithoutASubscriptionAwareHandlerThereAreNoSubscriptionMethods(): void
    {
        $locator = new PaymentHandlerLocator([new FakePaymentMethodHandler(PaymentMethod::PAYPAL)]);

        $this->assertSame([], $locator->getSubscriptionMethods());
    }

    public function testAnEmptyLocatorFindsNothing(): void
    {
        $locator = new PaymentHandlerLocator([]);

        $this->assertSame([], $locator->getPaymentMethods());
        $this->assertNull($locator->findByPaymentMethod('paypal'));
    }
}
