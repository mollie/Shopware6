<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\PaymentMethodSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

/**
 * Which Mollie method a Shopware payment method stands for is stored in its custom fields. The
 * checkout reads it from this extension to decide what to send to Mollie, so a method loaded
 * without it is treated as a non-Mollie method.
 */
#[CoversClass(PaymentMethodSubscriber::class)]
final class PaymentMethodSubscriberTest extends TestCase
{
    public function testEveryLoadedPaymentMethodIsHydrated(): void
    {
        $this->assertArrayHasKey(
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT,
            PaymentMethodSubscriber::getSubscribedEvents()
        );
    }

    public function testTheMollieMethodIsReadFromTheCustomFields(): void
    {
        $paymentMethod = $this->paymentMethod(['mollie_payment_method_name' => PaymentMethod::IDEAL->value]);

        (new PaymentMethodSubscriber())->onPaymentMethodLoaded($this->event($paymentMethod));

        $extension = $paymentMethod->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(PaymentMethodExtension::class, $extension);
        $this->assertSame(PaymentMethod::IDEAL, $extension->getPaymentMethod());
        $this->assertSame('payment-method-1', $extension->getId());
    }

    public function testAPaymentMethodOfAnotherProviderIsLeftAlone(): void
    {
        $paymentMethod = $this->paymentMethod(['some_other_field' => 'value']);

        (new PaymentMethodSubscriber())->onPaymentMethodLoaded($this->event($paymentMethod));

        $this->assertFalse($paymentMethod->hasExtension(Mollie::EXTENSION));
    }

    /**
     * A method that was removed from the plugin keeps its custom field on existing shops. It must
     * not be treated as a Mollie method any more, or the checkout would send an unknown method.
     */
    public function testAMethodNameMollieNoLongerKnowsIsLeftAlone(): void
    {
        $paymentMethod = $this->paymentMethod(['mollie_payment_method_name' => 'a_method_that_was_removed']);

        (new PaymentMethodSubscriber())->onPaymentMethodLoaded($this->event($paymentMethod));

        $this->assertFalse($paymentMethod->hasExtension(Mollie::EXTENSION));
    }

    public function testAnExtensionThatIsAlreadyThereIsNotReplaced(): void
    {
        $paymentMethod = $this->paymentMethod(['mollie_payment_method_name' => PaymentMethod::IDEAL->value]);
        $alreadyLoaded = new PaymentMethodExtension('payment-method-1', PaymentMethod::CREDIT_CARD);
        $paymentMethod->addExtension(Mollie::EXTENSION, $alreadyLoaded);

        (new PaymentMethodSubscriber())->onPaymentMethodLoaded($this->event($paymentMethod));

        $this->assertSame($alreadyLoaded, $paymentMethod->getExtension(Mollie::EXTENSION));
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function paymentMethod(array $customFields): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment-method-1');
        $paymentMethod->setCustomFields($customFields);
        $paymentMethod->setTranslated(['customFields' => $customFields]);

        return $paymentMethod;
    }

    /**
     * @return EntityLoadedEvent<PaymentMethodEntity>
     */
    private function event(PaymentMethodEntity $paymentMethod): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new PaymentMethodDefinition(), [$paymentMethod], Context::createDefaultContext());
    }
}
