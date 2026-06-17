<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cart;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidGuestAccountError;
use Mollie\Shopware\Component\Subscription\Cart\Error\PaymentMethodAvailabilityNotice;
use Mollie\Shopware\Component\Subscription\Cart\Validator\AvailabilityInformationValidator;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;

#[CoversClass(AvailabilityInformationValidator::class)]
final class AvailabilityInformationValidatorTest extends TestCase
{
    public function testValidateClearsExistingNoticeWhenSubscriptionsAreDisabled(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::subscription('item-1')->build())
            ->withError(new PaymentMethodAvailabilityNotice('item-1'))
            ->build()
        ;

        $validator = $this->getValidator(enabled: false);
        $validator->validate($cart, new ErrorCollection(), new FakeSalesChannelContext());

        $this->assertCount(0, $cart->getErrors());
    }

    public function testValidateClearsExistingNoticeWhenNoSubscriptionProductInCart(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::regular('item-1')->build())
            ->withError(new PaymentMethodAvailabilityNotice('item-1'))
            ->build()
        ;

        $validator = $this->getValidator(enabled: true);
        $validator->validate($cart, new ErrorCollection(), new FakeSalesChannelContext());

        $this->assertCount(0, $cart->getErrors());
    }

    public function testValidateAddsNoticeWithFirstSubscriptionLineItemIdWhenPresent(): void
    {
        $cart = CartBuilder::create()
            ->withLineItems([
                LineItemBuilder::regular('regular-1')->build(),
                LineItemBuilder::subscription('subscription-2')->build(),
                LineItemBuilder::subscription('subscription-3')->build(),
            ])
            ->build()
        ;

        $errors = new ErrorCollection();
        $validator = $this->getValidator(enabled: true);
        $validator->validate($cart, $errors, new FakeSalesChannelContext());

        $this->assertCount(1, $errors);
        $notice = $errors->first();
        $this->assertInstanceOf(PaymentMethodAvailabilityNotice::class, $notice);
        $this->assertSame(PaymentMethodAvailabilityNotice::KEY, $notice->getId());
        $this->assertSame(['lineItemId' => 'subscription-2'], $notice->getParameters());
    }

    public function testValidatePreservesUnrelatedErrorsWhenClearing(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::regular('item-1')->build())
            ->withError(new PaymentMethodAvailabilityNotice('item-1'))
            ->withError(new InvalidGuestAccountError())
            ->build()
        ;

        $validator = $this->getValidator(enabled: true);
        $validator->validate($cart, new ErrorCollection(), new FakeSalesChannelContext());

        $this->assertCount(1, $cart->getErrors());
        $this->assertInstanceOf(InvalidGuestAccountError::class, $cart->getErrors()->first());
    }

    private function getValidator(bool $enabled): AvailabilityInformationValidator
    {
        $settingsService = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: $enabled));

        return new AvailabilityInformationValidator($settingsService, new LineItemAnalyzer());
    }
}
