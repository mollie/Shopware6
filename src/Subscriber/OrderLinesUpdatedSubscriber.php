<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Event\OrderLinesUpdatedEvent;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Types\OrderLineType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderLinesUpdatedSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderLinesUpdatedEvent::class => 'onOrderLinesUpdated',
        ];
    }

    public function onOrderLinesUpdated(OrderLinesUpdatedEvent $event): void
    {
        $mollieOrder = $event->getMollieOrder();

        $shippingOptions = [];

        $mollieLines = $mollieOrder->lines();

        /**
         * @var OrderLine $line
         */
        foreach ($mollieLines as $line) {
            if ($line->type === OrderLineType::TYPE_SHIPPING_FEE && $line->shippableQuantity > 0) {
                $shippingOptions[] = [
                    'id' => $line->id,
                    'quantity' => $line->quantity,
                ];
            }
        }

        if (count($shippingOptions) === 0) {
            return;
        }
        try {
            $mollieOrder->createShipment(['lines' => $shippingOptions]);
        } catch (\Exception $exception) {
            $this->logger->error('Failed to update shipping costs', ['message' => $exception->getMessage(), 'options' => $shippingOptions]);
        }
    }
}
