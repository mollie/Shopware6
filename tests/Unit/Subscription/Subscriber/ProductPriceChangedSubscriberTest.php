<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Subscriber;

use Mollie\Shopware\Component\Subscription\Subscriber\ProductPriceChangedSubscriber;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionPriceCheckFlagger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

#[CoversClass(ProductPriceChangedSubscriber::class)]
final class ProductPriceChangedSubscriberTest extends TestCase
{
    public function testProductWriteWithPriceFlagsProduct(): void
    {
        $flagger = new FakeSubscriptionPriceCheckFlagger();
        $subscriber = new ProductPriceChangedSubscriber($flagger);

        $subscriber->onProductWritten($this->productWrittenEvent('product-id', ['price' => [['gross' => 9.99]]]));

        $this->assertSame(['product-id'], $flagger->getFlaggedProductIds());
    }

    public function testProductWriteWithoutPriceDoesNotFlag(): void
    {
        $flagger = new FakeSubscriptionPriceCheckFlagger();
        $subscriber = new ProductPriceChangedSubscriber($flagger);

        // e.g. a stock-only update must not trigger a price re-check.
        $subscriber->onProductWritten($this->productWrittenEvent('product-id', ['stock' => 5]));

        $this->assertSame([], $flagger->getFlaggedProductIds());
    }

    public function testProductPriceWriteFlagsReferencedProduct(): void
    {
        $flagger = new FakeSubscriptionPriceCheckFlagger();
        $subscriber = new ProductPriceChangedSubscriber($flagger);

        $writeResult = new EntityWriteResult(
            'price-id',
            ['productId' => 'product-id'],
            'product_price',
            EntityWriteResult::OPERATION_UPDATE
        );
        $event = new EntityWrittenEvent('product_price', [$writeResult], Context::createDefaultContext());

        $subscriber->onProductPriceWritten($event);

        $this->assertSame(['product-id'], $flagger->getFlaggedProductIds());
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function productWrittenEvent(string $productId, array $payload): EntityWrittenEvent
    {
        $writeResult = new EntityWriteResult($productId, $payload, 'product', EntityWriteResult::OPERATION_UPDATE);

        return new EntityWrittenEvent('product', [$writeResult], Context::createDefaultContext());
    }
}
