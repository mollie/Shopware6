<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Action;

use Mollie\Shopware\Component\Shipment\Route\ShipOrderRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('flow.action', ['key' => 'action.mollie.order.ship', 'priority' => 900])]
final class ShipOrderAction extends FlowAction implements EventSubscriberInterface
{
    public function __construct(
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
        $this->shipOrder($flow->getStore('orderId'), $flow->getContext());
    }

    private function shipOrder(string $orderId, Context $context): void
    {
        try {
            $this->logger->info('Starting shipment through Flow Builder Action for order: ' . $orderId);

            // Without items the route ships everything that is still open.
            $request = new Request([], ['orderId' => $orderId]);
            $this->shipOrderRoute->ship($request, $context);
        } catch (\Exception $ex) {
            $this->logger->error('Error when shipping order with Flow Builder Action', [
                'error' => $ex->getMessage(),
                'orderId' => $orderId,
            ]);

            throw $ex;
        }
    }
}
