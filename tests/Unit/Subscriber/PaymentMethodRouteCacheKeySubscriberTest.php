<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Subscriber\PaymentMethodRouteCacheKeySubscriber;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shopware caches the payment-method route. The Mollie removers hide methods depending on cart
 * amount, currency, billing country and whether the cart holds a subscription product, so unless
 * those same factors reach the cache key the cached response keeps offering removed methods.
 *
 * Every test therefore asks the same question: do two situations that must show different payment
 * methods end up with different cache keys?
 */
#[CoversClass(PaymentMethodRouteCacheKeySubscriber::class)]
final class PaymentMethodRouteCacheKeySubscriberTest extends TestCase
{
    public function testTheSubscriberListensToTheCacheKeyEvent(): void
    {
        $this->assertSame(
            [PaymentMethodRouteCacheKeyEvent::class => 'onGenerateCacheKey'],
            PaymentMethodRouteCacheKeySubscriber::getSubscribedEvents()
        );
    }

    public function testTheExistingCachePartsAreKept(): void
    {
        $event = $this->event(['original-part'], $this->cart(50.0));

        $this->subscriber()->onGenerateCacheKey($event);

        $parts = $event->getParts();
        $this->assertSame('original-part', $parts[0]);
        $this->assertCount(2, $parts);
    }

    public function testACartAmountChangeChangesTheCacheKey(): void
    {
        $this->assertNotSame(
            $this->keyFor($this->cart(50.0)),
            $this->keyFor($this->cart(500.0))
        );
    }

    public function testTheSameCartAmountKeepsTheCacheKey(): void
    {
        $this->assertSame(
            $this->keyFor($this->cart(50.0)),
            $this->keyFor($this->cart(50.0))
        );
    }

    /**
     * The availability remover applies the Mollie amount limits only when the setting is on, so
     * turning it on has to invalidate the cached response.
     */
    public function testTheMollieLimitSettingChangesTheCacheKey(): void
    {
        $withoutLimits = $this->keyFor($this->cart(50.0), new PaymentSettings('', 0));
        $withLimits = $this->keyFor($this->cart(50.0), new PaymentSettings('', 0, useMollieLimits: true));

        $this->assertNotSame($withoutLimits, $withLimits);
    }

    /**
     * A cart with a subscription product only offers the methods that support a recurring mandate.
     */
    public function testASubscriptionInTheCartChangesTheCacheKey(): void
    {
        $this->assertNotSame(
            $this->keyFor($this->cart(50.0)),
            $this->keyFor($this->subscriptionCart(50.0))
        );
    }

    public function testTheCurrencyChangesTheCacheKey(): void
    {
        $this->assertNotSame(
            $this->keyFor($this->cart(50.0), null, $this->context('EUR')),
            $this->keyFor($this->cart(50.0), null, $this->context('CHF'))
        );
    }

    /**
     * The billing country decides which methods are available, so two customers with different
     * billing addresses must not share a cached response.
     */
    public function testTheBillingAddressChangesTheCacheKey(): void
    {
        $this->assertNotSame(
            $this->keyFor($this->cart(50.0), null, $this->contextWithBillingAddress('address-1')),
            $this->keyFor($this->cart(50.0), null, $this->contextWithBillingAddress('address-2'))
        );
    }

    public function testAGuestWithoutACustomerStillGetsACacheKey(): void
    {
        $event = $this->event([], $this->cart(50.0));

        $this->subscriber()->onGenerateCacheKey($event);

        $this->assertCount(1, $event->getParts());
    }

    /**
     * Reading the cart through the cart service overwrites the rule ids of the context - on the
     * edit-order page the cart is empty, which would reset them. They have to survive.
     */
    public function testTheRuleIdsOfTheContextSurviveTheCartLookup(): void
    {
        $context = $this->context('EUR');
        $context->setRuleIds(['rule-1', 'rule-2']);

        $this->subscriber($this->cart(50.0))->onGenerateCacheKey($this->event([], $this->cart(50.0), $context));

        $this->assertSame(['rule-1', 'rule-2'], $context->getRuleIds());
    }

    // ----------------------------------------------------------------- helpers

    private function keyFor(Cart $cart, ?PaymentSettings $paymentSettings = null, ?FakeSalesChannelContext $context = null): string
    {
        $event = $this->event([], $cart, $context ?? $this->context('EUR'));

        $this->subscriber($cart, $paymentSettings)->onGenerateCacheKey($event);

        $parts = $event->getParts();

        return (string) end($parts);
    }

    private function subscriber(?Cart $cart = null, ?PaymentSettings $paymentSettings = null): PaymentMethodRouteCacheKeySubscriber
    {
        return new PaymentMethodRouteCacheKeySubscriber(
            new FakeSettingsService(paymentSettings: $paymentSettings ?? new PaymentSettings('', 0)),
            new LineItemAnalyzer(),
            new FakeCartService($cart ?? $this->cart(50.0))
        );
    }

    /**
     * @param array<mixed> $parts
     */
    private function event(array $parts, Cart $cart, ?FakeSalesChannelContext $context = null): PaymentMethodRouteCacheKeyEvent
    {
        return new PaymentMethodRouteCacheKeyEvent(
            $parts,
            new Request(),
            $context ?? $this->context('EUR'),
            null
        );
    }

    private function context(string $currencyIsoCode): FakeSalesChannelContext
    {
        $currency = new CurrencyEntity();
        $currency->setId('currency-' . $currencyIsoCode);
        $currency->setIsoCode($currencyIsoCode);

        $context = new FakeSalesChannelContext();
        $context->setCurrency($currency);
        $context->setRuleIds([]);

        return $context;
    }

    private function contextWithBillingAddress(string $addressId): FakeSalesChannelContext
    {
        $address = new CustomerAddressEntity();
        $address->setId($addressId);

        $context = $this->context('EUR');
        $context->setCustomer(CustomerBuilder::create()->withDefaultBillingAddress($address)->build());

        return $context;
    }

    private function cart(float $totalPrice): Cart
    {
        $cart = new Cart('cart-token');
        $cart->setPrice(new CartPrice(
            $totalPrice,
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        return $cart;
    }

    private function subscriptionCart(float $totalPrice): Cart
    {
        $cart = $this->cart($totalPrice);
        $cart->setLineItems(
            CartBuilder::create()
                ->withLineItem(LineItemBuilder::subscription('subscription-line-item')->build())
                ->build()
                ->getLineItems()
        );

        return $cart;
    }
}
