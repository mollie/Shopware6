<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions;


use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Service\OrderServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;


class RefundOrderAction extends FlowAction
{

    /**
     * @var OrderServiceInterface
     */
    private $orderService;

    /**
     * @var RefundManagerInterface
     */
    private $refundManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param OrderServiceInterface $orderService
     * @param RefundManagerInterface $refundManager
     * @param LoggerInterface $logger
     */
    public function __construct(OrderServiceInterface $orderService, RefundManagerInterface $refundManager, LoggerInterface $logger)
    {
        $this->orderService = $orderService;
        $this->refundManager = $refundManager;
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

            $request = new RefundRequest(
                (string)$order->getOrderNumber(),
                'Refund through Shopware Flow Builder',
                null
            );

            $this->refundManager->refund($order, $request, $baseEvent->getContext());

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
