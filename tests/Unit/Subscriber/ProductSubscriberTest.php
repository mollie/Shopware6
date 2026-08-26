<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\ProductSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

/**
 * Whether a product is a subscription or a voucher product is stored in its custom fields. The
 * cart and the Mollie payload read it from this extension, so a product loaded without it is
 * treated as an ordinary one-off article.
 */
#[CoversClass(ProductSubscriber::class)]
final class ProductSubscriberTest extends TestCase
{
    public function testEveryLoadedProductIsHydrated(): void
    {
        $this->assertArrayHasKey(
            ProductEvents::PRODUCT_LOADED_EVENT,
            ProductSubscriber::getSubscribedEvents()
        );
    }

    public function testASubscriptionProductIsRecognized(): void
    {
        $product = $this->product([
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 3,
            'mollie_payments_product_subscription_interval_unit' => 'months',
        ]);

        (new ProductSubscriber())->onProductLoaded($this->event($product));

        $extension = $product->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Product::class, $extension);
        $this->assertTrue($extension->isSubscription());
    }

    public function testAnOrdinaryProductGetsAnExtensionThatIsNoSubscription(): void
    {
        $product = $this->product([]);

        (new ProductSubscriber())->onProductLoaded($this->event($product));

        $extension = $product->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Product::class, $extension);
        $this->assertFalse($extension->isSubscription());
    }

    /**
     * A product whose custom fields were not loaded carries no information to hydrate from.
     */
    public function testAProductWithoutCustomFieldsIsLeftAlone(): void
    {
        $product = new ProductEntity();
        $product->setId('product-1');
        $product->setTranslated(['customFields' => null]);

        (new ProductSubscriber())->onProductLoaded($this->event($product));

        $this->assertFalse($product->hasExtension(Mollie::EXTENSION));
    }

    public function testAnExtensionThatIsAlreadyThereIsNotReplaced(): void
    {
        $product = $this->product(['mollie_payments_product_subscription_enabled' => true]);
        $alreadyLoaded = new Product();
        $product->addExtension(Mollie::EXTENSION, $alreadyLoaded);

        (new ProductSubscriber())->onProductLoaded($this->event($product));

        $this->assertSame($alreadyLoaded, $product->getExtension(Mollie::EXTENSION));
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function product(array $customFields): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId('product-1');
        $product->setCustomFields($customFields);
        $product->setTranslated(['customFields' => $customFields]);

        return $product;
    }

    /**
     * @return EntityLoadedEvent<ProductEntity>
     */
    private function event(ProductEntity $product): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new ProductDefinition(), [$product], Context::createDefaultContext());
    }
}
