<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Mollie\Shopware\Component\Shipment\ShipmentPersister;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(ShipmentPersister::class)]
final class ShipmentPersisterTest extends TestCase
{
    private FakeOrderRepository $lineItemRepository;

    private FakeOrderRepository $deliveryRepository;

    private EventSpy $eventDispatcher;

    private ShipmentPersister $persister;

    protected function setUp(): void
    {
        $this->lineItemRepository = new FakeOrderRepository();
        $this->deliveryRepository = new FakeOrderRepository();
        $this->eventDispatcher = new EventSpy();

        $this->persister = new ShipmentPersister(
            $this->lineItemRepository,
            $this->deliveryRepository,
            new FakeOrderService(),
            $this->eventDispatcher,
            new NullLogger(),
        );
    }

    public function testPersistStampsMollieIdOnUpsertsAndDispatchesEvent(): void
    {
        $lineUpserts = [['id' => 'li1', 'customFields' => [Mollie::EXTENSION => ['quantity' => 1]]]];
        $deliveryUpserts = [['id' => 'd1', 'customFields' => [Mollie::EXTENSION => ['quantity' => 1]]]];
        $event = new OrderShippedEvent('tx-1', Context::createDefaultContext());

        $response = $this->persister->persist($lineUpserts, $deliveryUpserts, 'cap_1', 'captureId', 'order-1', $event, true, Context::createDefaultContext());

        $lineUpsert = $this->lineItemRepository->getUpserts()[0];
        self::assertSame('cap_1', $lineUpsert['customFields'][Mollie::EXTENSION]['captureId']);

        $deliveryUpsert = $this->deliveryRepository->getUpserts()[0];
        self::assertSame('cap_1', $deliveryUpsert['customFields'][Mollie::EXTENSION]['captureId']);

        self::assertSame($event, $this->eventDispatcher->getEvent());
        self::assertSame('cap_1', $response->getMollieId());
    }

    public function testPersistWithoutDeliveriesStillUpsertsLinesAndDispatches(): void
    {
        $lineUpserts = [['id' => 'li1', 'customFields' => [Mollie::EXTENSION => ['quantity' => 1]]]];
        $event = new OrderShippedEvent('tx-1', Context::createDefaultContext());

        $this->persister->persist($lineUpserts, [], 'shp_1', 'shipmentId', 'order-1', $event, false, Context::createDefaultContext());

        self::assertSame('shp_1', $this->lineItemRepository->getUpserts()[0]['customFields'][Mollie::EXTENSION]['shipmentId']);
        self::assertSame(0, $this->deliveryRepository->getUpsertCount());
        self::assertInstanceOf(OrderShippedEvent::class, $this->eventDispatcher->getEvent());
    }
}
