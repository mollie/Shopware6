<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Subscriber;

use Mollie\Shopware\Component\Subscription\Subscriber\ShippingPriceChangedSubscriber;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionPriceCheckFlagger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

#[CoversClass(ShippingPriceChangedSubscriber::class)]
final class ShippingPriceChangedSubscriberTest extends TestCase
{
    public function testShippingPriceWriteFlagsShippingMethod(): void
    {
        $flagger = new FakeSubscriptionPriceCheckFlagger();
        $subscriber = new ShippingPriceChangedSubscriber($flagger);

        $writeResult = new EntityWriteResult(
            'price-id',
            ['shippingMethodId' => 'shipping-method-id'],
            'shipping_method_price',
            EntityWriteResult::OPERATION_UPDATE
        );
        $event = new EntityWrittenEvent('shipping_method_price', [$writeResult], Context::createDefaultContext());

        $subscriber->onShippingPriceWritten($event);

        $this->assertSame(['shipping-method-id'], $flagger->getFlaggedShippingMethodIds());
    }

    public function testDeletedShippingPriceIsIgnored(): void
    {
        $flagger = new FakeSubscriptionPriceCheckFlagger();
        $subscriber = new ShippingPriceChangedSubscriber($flagger);

        $writeResult = new EntityWriteResult(
            'price-id',
            ['shippingMethodId' => 'shipping-method-id'],
            'shipping_method_price',
            EntityWriteResult::OPERATION_DELETE
        );
        $event = new EntityWrittenEvent('shipping_method_price', [$writeResult], Context::createDefaultContext());

        $subscriber->onShippingPriceWritten($event);

        $this->assertSame([], $flagger->getFlaggedShippingMethodIds());
    }
}
