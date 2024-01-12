<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions;

use Kiener\MolliePayments\Components\ShipmentManager\ShipmentManagerInterface;
use Kiener\MolliePayments\Service\OrderServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShipOrderAction extends FlowAction implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderServiceInterface
     */
    private $orderService;

    /**
     * @var ShipmentManagerInterface
     */
    private $shipment;


    /**
     * @param OrderServiceInterface $orderService
     * @param ShipmentManagerInterface $shipment
     * @param LoggerInterface $logger
     */
    public function __construct(OrderServiceInterface $orderService, ShipmentManagerInterface $shipment, LoggerInterface $logger)
    {
        $this->orderService = $orderService;
        $this->shipment = $shipment;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'action.mollie.order.ship';
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            self::getName() => 'handle',
        ];
    }

    /**
     * @return string[]
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    /**
     * @param StorableFlow $flow
     * @throws \Exception
     * @return void
     */
    public function handleFlow(StorableFlow $flow): void
    {
        $orderId = $flow->getStore('orderId');

        $this->shipOrder($orderId, $flow->getContext());
    }

    /**
     * @param FlowEvent $event
     * @throws \Exception
     */
    public function handle(FlowEvent $event): void
    {
        $config = $event->getConfig();

        if (empty($config)) {
            return;
        }

        $baseEvent = $event->getEvent();

        if (!$baseEvent instanceof OrderAware) {
            return;
        }

        $orderId = $baseEvent->getOrderId();

        $this->shipOrder($orderId, $baseEvent->getContext());
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @throws \Exception
     * @return void
     */
    private function shipOrder(string $orderId, Context $context): void
    {
        $orderNumber = '';

        try {
            $order = $this->orderService->getOrder($orderId, $context);

            $orderNumber = $order->getOrderNumber();

            $this->logger->info('Starting Shipment through Flow Builder Action for order: ' . $orderNumber);

            # ship (all or) the rest of the order without providing any specific tracking information.
            # this will ensure tracking data is automatically taken from the order
            $this->shipment->shipOrderRest($order, null, $context);
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error when shipping order with Flow Builder Action',
                [
                    'error' => $ex->getMessage(),
                    'order' => $orderNumber,
                ]
            );

            throw $ex;
        }
    }
}
