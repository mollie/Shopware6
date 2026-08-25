<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover;

use Mollie\Shopware\Component\Mollie\VoucherCategory;
use Mollie\Shopware\Component\Mollie\VoucherCategoryCollection;
use Mollie\Shopware\Component\Payment\Method\VoucherPayment;
use Mollie\Shopware\Component\Payment\MethodRemover\VoucherPaymentMethodRemover;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;

#[CoversClass(VoucherPaymentMethodRemover::class)]
final class VoucherPaymentMethodRemoverTest extends TestCase
{
    private const ORDER_ID = 'order-id';

    public function testCollectionWithoutTheVoucherMethodIsReturnedUnchanged(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            PaymentMethodBuilder::create()->withId('paypal-id')->withHandlerIdentifier('Some\Other\PaymentHandler')->build(),
        ]);

        $result = $this->makeRemover(CartBuilder::create()->build())->remove($paymentMethods, '', new FakeSalesChannelContext());

        $this->assertCount(1, $result);
    }

    public function testVoucherIsRemovedForACartWithoutVoucherProducts(): void
    {
        $cart = CartBuilder::create()->withLineItem($this->makeCartLineItem('line-1'))->build();

        $result = $this->makeRemover($cart)->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertNull($result->get('voucher-id'));
        $this->assertNotNull($result->get('paypal-id'));
    }

    public function testVoucherIsKeptForACartWithAVoucherProduct(): void
    {
        $cart = CartBuilder::create()->withLineItem($this->makeCartLineItem('line-1', VoucherCategory::ECO))->build();

        $result = $this->makeRemover($cart)->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertNotNull($result->get('voucher-id'));
    }

    public function testCartLineItemWithAnEmptyVoucherCategoryListDoesNotKeepTheVoucher(): void
    {
        $lineItem = $this->makeCartLineItem('line-1');
        $lineItem->addExtension(Mollie::EXTENSION, new Product());
        $cart = CartBuilder::create()->withLineItem($lineItem)->build();

        $result = $this->makeRemover($cart)->remove($this->makePaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertNull($result->get('voucher-id'));
    }

    public function testVoucherIsRemovedForAnOrderWithoutVoucherProducts(): void
    {
        $orderRepository = new FakeOrderSearchRepository();
        $orderRepository->add($this->makeOrder($this->makeOrderLineItem('line-1')));

        $result = $this->makeRemover(CartBuilder::create()->build(), $orderRepository)
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext());

        $this->assertNull($result->get('voucher-id'));
    }

    public function testVoucherIsKeptForAnOrderWithAVoucherProduct(): void
    {
        $orderRepository = new FakeOrderSearchRepository();
        $orderRepository->add($this->makeOrder($this->makeOrderLineItem('line-1', VoucherCategory::MEAL)));

        $result = $this->makeRemover(CartBuilder::create()->build(), $orderRepository)
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext());

        $this->assertNotNull($result->get('voucher-id'));
    }

    public function testVoucherIsRemovedForAnOrderWithoutLineItems(): void
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $orderRepository = new FakeOrderSearchRepository();
        $orderRepository->add($order);

        $result = $this->makeRemover(CartBuilder::create()->build(), $orderRepository)
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext());

        $this->assertNull($result->get('voucher-id'));
    }

    public function testVoucherIsRemovedWhenTheOrderCannotBeLoaded(): void
    {
        $result = $this->makeRemover(CartBuilder::create()->build(), new FakeOrderSearchRepository())
            ->remove($this->makePaymentMethods(), self::ORDER_ID, new FakeSalesChannelContext());

        $this->assertNull($result->get('voucher-id'));
    }

    private function makeRemover(Cart $cart, ?FakeOrderSearchRepository $orderRepository = null): VoucherPaymentMethodRemover
    {
        return new VoucherPaymentMethodRemover(new FakeCartService($cart), $orderRepository ?? new FakeOrderSearchRepository());
    }

    private function makePaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection([
            PaymentMethodBuilder::create()->withId('voucher-id')->withHandlerIdentifier(VoucherPayment::class)->build(),
            PaymentMethodBuilder::create()->withId('paypal-id')->withHandlerIdentifier('Some\Other\PaymentHandler')->build(),
        ]);
    }

    private function makeCartLineItem(string $id, ?VoucherCategory $voucherCategory = null): CartLineItem
    {
        $lineItem = new CartLineItem($id, CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        if ($voucherCategory !== null) {
            $lineItem->addExtension(Mollie::EXTENSION, $this->makeVoucherProduct($voucherCategory));
        }

        return $lineItem;
    }

    private function makeOrderLineItem(string $id, ?VoucherCategory $voucherCategory = null): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setLabel('Product ' . $id);
        if ($voucherCategory !== null) {
            $lineItem->addExtension(Mollie::EXTENSION, $this->makeVoucherProduct($voucherCategory));
        }

        return $lineItem;
    }

    private function makeVoucherProduct(VoucherCategory $voucherCategory): Product
    {
        $product = new Product();
        $product->setVoucherCategories(new VoucherCategoryCollection([$voucherCategory]));

        return $product;
    }

    private function makeOrder(OrderLineItemEntity ...$lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }
}
