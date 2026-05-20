<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\SubscriptionRemover;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionAwarePaymentHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionLineItemsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

#[CoversClass(SubscriptionRemover::class)]
final class SubscriptionRemoverTest extends TestCase
{
    public function testRemoveReturnsAllMethodsWhenSubscriptionsAreDisabled(): void
    {
        $resolver = new FakeSubscriptionLineItemsResolver(new LineItemCollection([LineItemBuilder::subscription('item-0')->build()]));
        $remover = $this->getRemover($resolver, enabled: false);

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(2, $result);
        $this->assertSame(0, $resolver->getCallCount());
    }

    public function testRemoveReturnsAllMethodsWhenNoSubscriptionProductInLineItems(): void
    {
        $resolver = new FakeSubscriptionLineItemsResolver(new LineItemCollection([LineItemBuilder::regular('item-0')->build()]));
        $remover = $this->getRemover($resolver, enabled: true);

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(2, $result);
    }

    public function testRemoveFiltersOutNonSubscriptionAwareMethodsWhenSubscriptionProductPresent(): void
    {
        $resolver = new FakeSubscriptionLineItemsResolver(new LineItemCollection([
            LineItemBuilder::regular('item-0')->build(),
            LineItemBuilder::subscription('item-1')->build(),
        ]));
        $remover = $this->getRemover($resolver, enabled: true);

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(1, $result);
        $this->assertNotNull($result->get('subscription-aware-id'));
        $this->assertNull($result->get('regular-id'));
    }

    public function testRemovePassesOrderIdToResolver(): void
    {
        $resolver = new FakeSubscriptionLineItemsResolver(new LineItemCollection([LineItemBuilder::regular('item-0')->build()]));
        $remover = $this->getRemover($resolver, enabled: true);

        $remover->remove($this->buildPaymentMethods(), 'order-id-42', new FakeSalesChannelContext());

        $this->assertSame(1, $resolver->getCallCount());
        $this->assertSame('order-id-42', $resolver->getCalls()[0]['orderId']);
    }

    private function getRemover(FakeSubscriptionLineItemsResolver $resolver, bool $enabled): SubscriptionRemover
    {
        $settingsService = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: $enabled));

        $handlerLocator = new PaymentHandlerLocator([
            new FakeSubscriptionAwarePaymentHandler(),
            new FakePaymentMethodHandler(),
        ]);

        return new SubscriptionRemover($resolver, $handlerLocator, new LineItemAnalyzer(), $settingsService);
    }

    private function buildPaymentMethods(): PaymentMethodCollection
    {
        $subscriptionAware = PaymentMethodBuilder::create()
            ->withId('subscription-aware-id')
            ->withHandlerIdentifier(FakeSubscriptionAwarePaymentHandler::class)
            ->build()
        ;

        $regular = PaymentMethodBuilder::create()
            ->withId('regular-id')
            ->withHandlerIdentifier(FakePaymentMethodHandler::class)
            ->build()
        ;

        return new PaymentMethodCollection([$subscriptionAware, $regular]);
    }
}
