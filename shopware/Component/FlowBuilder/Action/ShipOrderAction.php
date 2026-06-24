<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Action;

use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

final class ShipOrderAction extends FlowAction implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        private readonly ShipOrderRoute $shipOrderRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getName(): string
    {
        return 'action.mollie.order.ship';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handleFlow',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $orderId = $flow->getStore('orderId');
        $this->shipOrder($orderId, $flow->getContext());
    }

    private function shipOrder(string $orderId, Context $context): void
    {
        $orderNumber = '';

        try {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociation('lineItems');

            $order = $this->orderRepository->search($criteria, $context)->first();

            if (! $order instanceof OrderEntity) {
                throw new \RuntimeException(sprintf('Order "%s" not found', $orderId));
            }

            $orderNumber = (string) $order->getOrderNumber();
            $items = $this->collectRemainingItems($order->getLineItems() ?? new OrderLineItemCollection());

            if (count($items) === 0) {
                $this->logger->info('ShipOrderAction: order already fully shipped, skipping', [
                    'orderId' => $orderId,
                    'orderNumber' => $orderNumber,
                ]);

                return;
            }

            $this->logger->info('Starting Shipment through Flow Builder Action for order: ' . $orderNumber);

            $request = new Request([], ['orderId' => $orderId, 'items' => $items]);
            $this->shipOrderRoute->ship($request, $context);
        } catch (\Exception $ex) {
            $this->logger->error('Error when shipping order with Flow Builder Action', [
                'error' => $ex->getMessage(),
                'order' => $orderNumber,
            ]);

            throw $ex;
        }
    }

    /**
     * @return list<array{id: string, quantity: int}>
     */
    private function collectRemainingItems(OrderLineItemCollection $lineItems): array
    {
        $items = [];

        foreach ($lineItems as $lineItem) {
            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shipped = (int) ($fields['quantity'] ?? 0);
            $cancelled = (int) ($fields['cancelled_quantity'] ?? 0);
            $remaining = $lineItem->getQuantity() - $shipped - $cancelled;

            if ($remaining > 0) {
                $items[] = ['id' => $lineItem->getId(), 'quantity' => $remaining];
            }
        }

        return $items;
    }
}
