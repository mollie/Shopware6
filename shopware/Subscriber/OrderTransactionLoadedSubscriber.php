<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Entity\OrderTransaction\OrderTransaction;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OrderTransactionLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_TRANSACTION_LOADED_EVENT => 'onOrderTransaction',
        ];
    }

    public function onOrderTransaction(EntityLoadedEvent $event): void
    {
        /** @var OrderTransactionEntity $orderTransaction */
        foreach ($event->getEntities() as $orderTransaction) {
            if (! $orderTransaction instanceof OrderTransactionEntity) {
                continue;
            }
            $mollieCustomFields = $orderTransaction->getCustomFields()[Mollie::EXTENSION] ?? null;
            if ($mollieCustomFields === null) {
                continue;
            }
            if (! isset($mollieCustomFields[OrderTransaction::PAYMENTS_API_FLAG])) {
                continue;
            }

            $orderTransaction->addExtension(Mollie::EXTENSION, new OrderTransaction(...$mollieCustomFields));
        }
    }
}
