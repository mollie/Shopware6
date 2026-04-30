<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cart;

use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidGuestAccountError;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidPaymentMethodError;
use Mollie\Shopware\Component\Subscription\Cart\Error\PaymentMethodAvailabilityNotice;
use Mollie\Shopware\Component\Subscription\Cart\Validator\SubscriptionCartValidator;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionAwarePaymentHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;

#[CoversClass(SubscriptionCartValidator::class)]
final class SubscriptionCartValidatorTest extends TestCase
{
    public function testValidateClearsExistingErrorsWhenSubscriptionsAreDisabled(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->withError(new InvalidGuestAccountError())
            ->withError(new InvalidPaymentMethodError())
            ->build();

        $customer = CustomerBuilder::create()->build();
        $context = $this->buildContext($customer, aware: true);

        $validator = $this->getValidator(enabled: false);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(0, $cart->getErrors());
        $this->assertCount(0, $errors);
    }

    public function testValidateReturnsEarlyWhenCartHasNoSubscription(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::regular('item-1')->build())
            ->build();

        $customer = CustomerBuilder::create()->build();
        $context = $this->buildContext($customer, aware: true);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testValidateReturnsEarlyWhenPaymentMethodIsNotAMollieHandler(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->build();

        $customer = CustomerBuilder::create()->asGuest()->build();
        $paymentMethod = PaymentMethodBuilder::create()
            ->withHandlerIdentifier('Some\\NonMollie\\Handler')
            ->build();

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);
        $context->setPaymentMethod($paymentMethod);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testValidateReturnsEarlyWhenCustomerIsNull(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->build();

        $context = $this->buildContext(null, aware: true);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testValidateAddsNoErrorsForRegisteredCustomerWithSubscriptionAwareHandler(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->build();

        $customer = CustomerBuilder::create()->build();
        $context = $this->buildContext($customer, aware: true);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testValidateAddsGuestErrorWhenCustomerIsGuest(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->build();

        $customer = CustomerBuilder::create()->asGuest()->build();
        $context = $this->buildContext($customer, aware: true);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(InvalidGuestAccountError::class, $errors->first());
    }

    public function testValidateAddsPaymentMethodErrorWhenHandlerIsNotSubscriptionAware(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->build();

        $customer = CustomerBuilder::create()->build();
        $context = $this->buildContext($customer, aware: false);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(InvalidPaymentMethodError::class, $errors->first());
    }

    public function testValidateAddsBothErrorsWhenGuestAndHandlerNotAware(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->build();

        $customer = CustomerBuilder::create()->asGuest()->build();
        $context = $this->buildContext($customer, aware: false);

        $validator = $this->getValidator(enabled: true);
        $errors = new ErrorCollection();
        $validator->validate($cart, $errors, $context);

        $this->assertCount(2, $errors);
    }

    public function testValidateOnlyClearsItsOwnErrorsAndPreservesOthers(): void
    {
        $unrelated = new PaymentMethodAvailabilityNotice('item-1');
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::regular('item-1')->build())
            ->withError(new InvalidGuestAccountError())
            ->withError(new InvalidPaymentMethodError())
            ->withError($unrelated)
            ->build();

        $customer = CustomerBuilder::create()->build();
        $context = $this->buildContext($customer, aware: true);

        $validator = $this->getValidator(enabled: true);
        $validator->validate($cart, new ErrorCollection(), $context);

        $this->assertCount(1, $cart->getErrors());
        $this->assertSame($unrelated, $cart->getErrors()->first());
    }

    private function getValidator(bool $enabled): SubscriptionCartValidator
    {
        $settingsService = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: $enabled));

        $handlerLocator = new PaymentHandlerLocator([
            new FakeSubscriptionAwarePaymentHandler(),
            new FakePaymentMethodHandler(),
        ]);

        return new SubscriptionCartValidator($settingsService, $handlerLocator, new LineItemAnalyzer());
    }

    private function buildContext(?CustomerEntity $customer, bool $aware): FakeSalesChannelContext
    {
        $handlerClass = $aware ? FakeSubscriptionAwarePaymentHandler::class : FakePaymentMethodHandler::class;
        $paymentMethod = PaymentMethodBuilder::create()
            ->withHandlerIdentifier($handlerClass)
            ->build();

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);
        $context->setPaymentMethod($paymentMethod);

        return $context;
    }
}
