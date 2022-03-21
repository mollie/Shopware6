<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions;


use Kiener\MolliePayments\Service\OrderServiceInterface;
use Kiener\MolliePayments\Service\Refund\RefundServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;


class RefundOrderAction extends FlowAction
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
     * @var RefundServiceInterface
     */
    private $refundService;


    /**
     * @param OrderServiceInterface $orderService
     * @param RefundServiceInterface $refundService
     * @param LoggerInterface $logger
     */
    public function __construct(OrderServiceInterface $orderService, RefundServiceInterface $refundService, LoggerInterface $logger)
    {
        $this->orderService = $orderService;
        $this->refundService = $refundService;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return 'action.mollie.order.refund';
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

        $this->refundOrder($baseEvent, $config);
    }

    /**
     * @param OrderAware $baseEvent
     * @param array<mixed> $config
     * @throws \Exception
     */
    private function refundOrder(OrderAware $baseEvent, array $config): void
    {
        $orderNumber = '';

        try {

            $orderId = $baseEvent->getOrderId();

            $order = $this->orderService->getOrder($orderId, $baseEvent->getContext());

            $orderNumber = $order->getOrderNumber();

            $this->logger->info('Starting Refund through Flow Builder Action for order: ' . $orderNumber);

            $this->refundService->refundPartial(
                $order,
                'Refund through Shopware Flow Builder',
                $order->getAmountTotal(),
                [],
                $baseEvent->getContext()
            );

        } catch (\Exception $ex) {

            $this->logger->error('Error when refunding order with Flow Builder Action',
                [
                    'error' => $ex->getMessage(),
                    'order' => $orderNumber,
                ]);

            throw $ex;
        }
    }

}
