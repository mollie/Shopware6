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

            if ($paymentId === null) {
                continue;
            }

            $method = $mollieCustomFields['method'] ?? null;
            $countPayments = $mollieCustomFields['countPayments'] ?? 1;
            $thirdPartyPaymentId = $mollieCustomFields['thirdPartyPaymentId'] ?? null;
            $authenticationId = $mollieCustomFields['authenticationId'] ?? null;
            $finalizeUrl = $mollieCustomFields['finalizeUrl'] ?? null;
            $orderId = $mollieCustomFields['orderId'] ?? null;
            $creditCardLabel = $mollieCustomFields['creditCardLabel'] ?? null;
            $creditCardNumber = $mollieCustomFields['creditCardNumber'] ?? null;
            $creditCardHolder = $mollieCustomFields['creditCardHolder'] ?? null;
            $paypalPayerId = $mollieCustomFields['paypalPayerId'] ?? null;
            $checkoutUrl = $mollieCustomFields['checkoutUrl'] ?? null;
            $changePaymentStateUrl = $mollieCustomFields['changePaymentStateUrl'] ?? null;

            $transactionExtension = new Payment($paymentId);
            $transactionExtension->setCountPayments($countPayments);
            if ($finalizeUrl !== null) {
                $transactionExtension->setFinalizeUrl($finalizeUrl);
            }
            if ($orderId !== null) {
                $transactionExtension->setOrderId($orderId);
            }
            if ($method !== null) {
                $transactionExtension->setMethod(PaymentMethod::from($method));
            }
            if ($thirdPartyPaymentId !== null) {
                $transactionExtension->setThirdPartyPaymentId($thirdPartyPaymentId);
            }
            if ($authenticationId !== null) {
                $transactionExtension->setAuthenticationId($authenticationId);
            }
            if ($creditCardLabel !== null) {
                $transactionExtension->setCreditCardLabel($creditCardLabel);
            }
            if ($creditCardNumber !== null) {
                $transactionExtension->setCreditCardNumber($creditCardNumber);
            }
            if ($creditCardHolder !== null) {
                $transactionExtension->setCreditCardHolder($creditCardHolder);
            }
            if ($paypalPayerId !== null) {
                $transactionExtension->setPaypalPayerId($paypalPayerId);
            }
            if ($checkoutUrl !== null) {
                $transactionExtension->setCheckoutUrl($checkoutUrl);
            }
            if ($changePaymentStateUrl !== null) {
                $transactionExtension->setChangePaymentStateUrl($changePaymentStateUrl);
            }

            $bankAccount = $mollieCustomFields['bankAccount'] ?? null;
            if ($bankAccount !== null) {
                $transactionExtension->setBankName((string) ($mollieCustomFields['bankName'] ?? ''));
                $transactionExtension->setBankAccount((string) $bankAccount);
                $transactionExtension->setBankBic((string) ($mollieCustomFields['bankBic'] ?? ''));
                $transactionExtension->setTransferReference((string) ($mollieCustomFields['transferReference'] ?? ''));
                $transactionExtension->setConsumerName((string) ($mollieCustomFields['consumerName'] ?? ''));
                $transactionExtension->setConsumerAccount((string) ($mollieCustomFields['consumerAccount'] ?? ''));
                $transactionExtension->setConsumerBic((string) ($mollieCustomFields['consumerBic'] ?? ''));
            }

            $orderTransaction->addExtension(Mollie::EXTENSION, $transactionExtension);
        }
    }
}
