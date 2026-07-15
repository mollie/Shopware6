<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Action;

use Mollie\Shopware\Component\Refund\Controller\RefundController;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('flow.action', ['key' => 'action.mollie.order.refund', 'priority' => 900])]
final class RefundOrderAction extends FlowAction implements EventSubscriberInterface
{
    private const DESCRIPTION = 'Refund through Shopware Flow Builder';

    public function __construct(
        private readonly RefundController $refundController,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getName(): string
    {
        return 'action.mollie.order.refund';
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
        $this->refundOrder($orderId, $flow->getContext());
    }

    private function refundOrder(string $orderId, Context $context): void
    {
        try {
            $this->logger->info('Starting Refund through Flow Builder Action', ['orderId' => $orderId]);

            $request = new Request([], ['orderId' => $orderId, 'description' => self::DESCRIPTION]);
            $this->refundController->create($request, $context);
        } catch (\Exception $ex) {
            $this->logger->error('Error when refunding order with Flow Builder Action', [
                'error' => $ex->getMessage(),
                'orderId' => $orderId,
            ]);

            throw $ex;
        }
    }
}
