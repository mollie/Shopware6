<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OrderTransactionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_TRANSACTION_LOADED_EVENT => 'onOrderTransaction',
        ];
    }

    /**
     * @param EntityLoadedEvent<OrderTransactionEntity> $event
     */
    public function onOrderTransaction(EntityLoadedEvent $event): void
    {
        /** @var OrderTransactionEntity $orderTransaction */
        foreach ($event->getEntities() as $orderTransaction) {
            if (! $orderTransaction instanceof OrderTransactionEntity) {
                continue;
            }
            if ($orderTransaction->hasExtension(Mollie::EXTENSION)) {
                continue;
            }
            $mollieCustomFields = $orderTransaction->getTranslated()['customFields'][Mollie::EXTENSION] ?? null;

            if ($mollieCustomFields === null) {
                $mollieCustomFields = $orderTransaction->getCustomFields()[Mollie::EXTENSION] ?? null;
            }

            if ($mollieCustomFields === null) {
                continue;
            }

            $paymentId = $mollieCustomFields['id'] ?? null;
            $finalizeUrl = $mollieCustomFields['finalizeUrl'] ?? null;
            if ($finalizeUrl === null || $paymentId === null) {
                continue;
            }

            $method = $mollieCustomFields['method'] ?? null;
            $countPayments = $mollieCustomFields['countPayments'] ?? 1;
            $thirdPartyPaymentId = $mollieCustomFields['thirdPartyPaymentId'] ?? null;
            $authenticationId = $mollieCustomFields['authenticationId'] ?? null;

            $transactionExtension = new Payment($paymentId);
            $transactionExtension->setCountPayments($countPayments);
            $transactionExtension->setFinalizeUrl($finalizeUrl);
            if ($method !== null) {
                $transactionExtension->setMethod(PaymentMethod::from($method));
            }
            if ($thirdPartyPaymentId !== null) {
                $transactionExtension->setThirdPartyPaymentId($thirdPartyPaymentId);
            }
            if ($authenticationId !== null) {
                $transactionExtension->setAuthenticationId($authenticationId);
            }

            $orderTransaction->addExtension(Mollie::EXTENSION, $transactionExtension);
        }
    }
}
