<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Mollie\CreateOrderRefund;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\RefundItemSplitter;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Refund\Fake\FakeStockStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

#[CoversClass(RefundPersister::class)]
final class RefundPersisterTest extends TestCase
{
    private FakeEntityRepository $refundRepository;

    private FakeStockStorage $stockStorage;

    private Context $context;

    protected function setUp(): void
    {
        $this->refundRepository = new FakeEntityRepository(new RefundDefinition());
        $this->stockStorage = new FakeStockStorage();
        $this->context = Context::createDefaultContext();
    }

    public function testStoredRefundRowPointsAtTheOrderAndTheMollieRefund(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'partial',
            'Public reason',
            'Internal note',
            [],
            [],
            [],
            $this->context
        );

        $refundRow = $this->refundRepository->data[0][0];

        $this->assertSame('order-1', $refundRow['orderId']);
        $this->assertSame('version-1', $refundRow['orderVersionId']);
        $this->assertSame('re_1', $refundRow['mollieRefundId']);
        $this->assertSame('partial', $refundRow['type']);
        $this->assertSame('Public reason', $refundRow['publicDescription']);
        $this->assertSame('Internal note', $refundRow['internalDescription']);
    }

    public function testARefundWithoutLinesStoresNoComposition(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'full',
            '',
            '',
            [],
            [],
            [],
            $this->context
        );

        $this->assertArrayNotHasKey('refundItems', $this->refundRepository->data[0][0]);
    }

    public function testCompositionIsBuiltFromTheLinesMollieRefunded(): void
    {
        $this->seedRepository();
        $line = $this->mollieLine('mollie-line-1', 'li-1', 'Product A', 2, 5.0);

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreateOrderRefund('ord_1', new LineItemCollection([$line])),
            'partial',
            '',
            '',
            [],
            [],
            [],
            $this->context
        );

        $this->assertSame(
            [
                [
                    'mollieLineId' => 'mollie-line-1',
                    'label' => 'Product A',
                    'quantity' => 2,
                    'amount' => 5.0,
                    'orderLineItemId' => 'li-1',
                    'orderLineItemVersionId' => 'version-1',
                    'orderDeliveryId' => null,
                ],
            ],
            $this->refundRepository->data[0][0]['refundItems']
        );
    }

    public function testShippingLineIsStoredAsDeliveryReferenceSoTheForeignKeyHolds(): void
    {
        $this->seedRepository();
        $shippingLine = $this->mollieLine('mollie-line-2', 'delivery-1', 'Shipping', 1, 4.99);
        $shippingLine->setType(LineItemType::SHIPPING);

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(4.99),
            new CreateOrderRefund('ord_1', new LineItemCollection([$shippingLine])),
            'partial',
            '',
            '',
            [],
            [],
            [],
            $this->context
        );

        $refundItem = $this->refundRepository->data[0][0]['refundItems'][0];

        $this->assertNull($refundItem['orderLineItemId']);
        $this->assertSame('delivery-1', $refundItem['orderDeliveryId']);
    }

    public function testCompositionIsBuiltFromTheRequestWhenMollieReturnedNoLines(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'partial',
            '',
            '',
            [['id' => 'li-1', 'label' => 'Product A', 'quantity' => 2, 'amount' => 10.0, 'resetStock' => 0]],
            [],
            ['li-1' => ['max' => 10.0, 'quantity' => 2]],
            $this->context
        );

        $refundItems = $this->refundRepository->data[0][0]['refundItems'];

        $this->assertSame(2, $refundItems[0]['quantity']);
        $this->assertSame(5.0, $refundItems[0]['amount']);
        $this->assertSame('li-1', $refundItems[0]['orderLineItemId']);
    }

    public function testTheStoredCompositionNeverExceedsTheAmountMollieActuallyRefunded(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(6.0),
            new CreatePaymentRefund('tr_1', new Money(6.0, 'EUR')),
            'partial',
            '',
            '',
            [['id' => 'li-1', 'label' => 'Product A', 'quantity' => 2, 'amount' => 10.0, 'resetStock' => 0]],
            [],
            ['li-1' => ['max' => 10.0, 'quantity' => 2]],
            $this->context
        );

        $refundItems = $this->refundRepository->data[0][0]['refundItems'];

        $this->assertCount(1, $refundItems);
        $this->assertSame(2, $refundItems[0]['quantity']);
        $this->assertSame(3.0, $refundItems[0]['amount']);
    }

    public function testRequestedItemWithoutAnAmountIsNotStored(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(0.0),
            new CreatePaymentRefund('tr_1', new Money(0.0, 'EUR')),
            'partial',
            '',
            '',
            [['id' => 'li-1', 'label' => 'Product A', 'quantity' => 1, 'amount' => 0.0, 'resetStock' => 0]],
            [],
            [],
            $this->context
        );

        $this->assertSame([], $this->refundRepository->data[0][0]['refundItems']);
    }

    public function testStockIsReturnedForTheRequestedQuantity(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'partial',
            '',
            '',
            [['id' => 'li-1', 'label' => 'Product A', 'quantity' => 2, 'amount' => 10.0, 'resetStock' => 2]],
            [],
            [],
            $this->context
        );

        $this->assertCount(1, $this->stockStorage->alterations);
        $this->assertSame('product-1', $this->stockStorage->alterations[0]->productId);
        $this->assertSame(2, $this->stockStorage->alterations[0]->quantityBefore);
    }

    public function testStockResetIsCappedAtTheQuantityThatWasOrdered(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'partial',
            '',
            '',
            [['id' => 'li-1', 'label' => 'Product A', 'quantity' => 2, 'amount' => 10.0, 'resetStock' => 99]],
            [],
            [],
            $this->context
        );

        $this->assertSame(2, $this->stockStorage->alterations[0]->quantityBefore);
    }

    public function testNoStockIsReturnedWhenTheMerchantDidNotAskForIt(): void
    {
        $this->seedRepository();

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'partial',
            '',
            '',
            [['id' => 'li-1', 'label' => 'Product A', 'quantity' => 2, 'amount' => 10.0, 'resetStock' => 0]],
            [],
            [],
            $this->context
        );

        $this->assertCount(0, $this->stockStorage->alterations);
    }

    public function testARefundThatCannotBeReadBackIsReported(): void
    {
        $this->seedRepository(withEntity: false);

        $this->expectException(\RuntimeException::class);

        $this->persister()->persist(
            $this->order(),
            $this->mollieRefund(10.0),
            new CreatePaymentRefund('tr_1', new Money(10.0, 'EUR')),
            'full',
            '',
            '',
            [],
            [],
            [],
            $this->context
        );
    }

    private function persister(): RefundPersister
    {
        return new RefundPersister($this->refundRepository, $this->stockStorage, new RefundItemSplitter());
    }

    private function seedRepository(bool $withEntity = true): void
    {
        $this->refundRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection(),
            []
        );

        $entities = [];
        if ($withEntity) {
            $entity = new RefundEntity();
            $entity->setId('refund-entity-1');
            $entities[] = $entity;
        }

        $this->refundRepository->entitySearchResults[] = new EntitySearchResult(
            'mollie_refund',
            count($entities),
            new RefundCollection($entities),
            null,
            new Criteria(),
            $this->context
        );
    }

    private function order(): OrderEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('li-1');
        $lineItem->setVersionId('version-1');
        $lineItem->setQuantity(2);
        $lineItem->setReferencedId('product-1');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-1');
        $delivery->setVersionId('version-1');

        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setVersionId('version-1');
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        return $order;
    }

    private function mollieRefund(float $amount): MollieRefund
    {
        return new MollieRefund(
            're_1',
            'tr_1',
            RefundStatus::Refunded,
            new Money($amount, 'EUR'),
            'Refund',
            new \DateTimeImmutable('2026-08-25 10:00:00')
        );
    }

    private function mollieLine(string $mollieId, string $shopwareId, string $description, int $quantity, float $unitPrice): LineItem
    {
        $line = new LineItem($description, $quantity, new Money($unitPrice, 'EUR'), new Money($unitPrice * $quantity, 'EUR'));
        $line->setId($mollieId);
        $line->setShopwareLineItemId($shopwareId);

        return $line;
    }
}
