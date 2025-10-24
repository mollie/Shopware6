<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Service\OrderServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundOrderAction extends FlowAction implements EventSubscriberInterface
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

    public function __construct(OrderServiceInterface $orderService, RefundManagerInterface $refundManager, LoggerInterface $logger)
    {
        $this->orderService = $orderService;
        $this->refundManager = $refundManager;
        $this->logger = $logger;
    }

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
     * @throws \Exception
     */
    public function handleFlow(StorableFlow $flow): void
    {
        $orderId = $flow->getStore('orderId');

        $this->refundOrder($orderId, $flow->getContext());
    }

    /**
     * @throws \Exception
     *
     * @phpstan-ignore class.notFound
     */
    public function handle(FlowEvent $event): void
    {
        /** @phpstan-ignore class.notFound */
        $config = $event->getConfig();

        if (empty($config)) {
            return;
        }
        /** @phpstan-ignore class.notFound */
        $baseEvent = $event->getEvent();

        if (! $baseEvent instanceof OrderAware) {
            return;
        }

        $orderId = $baseEvent->getOrderId();

        $this->refundOrder($orderId, $baseEvent->getContext());
    }

    /**
     * @throws \Exception
     */
    private function refundOrder(string $orderId, Context $context): void
    {
        $orderNumber = '';

        try {
            $order = $this->orderService->getOrder($orderId, $context);

            $orderNumber = $order->getOrderNumber();

            $this->logger->info('Starting Refund through Flow Builder Action for order: ' . $orderNumber);

            $request = new RefundRequest(
                (string) $order->getOrderNumber(),
                'Refund through Shopware Flow Builder',
                '',
                null
            );

            $this->refundManager->refund($order, $request, $context);
        } catch (\Exception $ex) {
            $this->logger->error(
                'Error when refunding order with Flow Builder Action',
                [
                    'error' => $ex->getMessage(),
                    'order' => $orderNumber,
                ]
            );

            throw $ex;
        }
    }
}
