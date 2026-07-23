<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rebuilds the Mollie Payment extension from the legacy Mollie custom fields stored on the order
 * (pre-5.0 data where the transaction no longer carries the extension) and writes it back onto the
 * transaction so subsequent reads find it. Returns null when the order has no recoverable Mollie data.
 */
final class OrderPaymentRecovery
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
    ) {
    }

    public function restore(OrderEntity $order, OrderTransactionEntity $transaction, Context $context): ?Payment
    {
        $orderMollieFields = ($order->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];

        $paymentId = (string) ($orderMollieFields['payment_id'] ?? '');
        $orderId = (string) ($orderMollieFields['order_id'] ?? '');

        if ($paymentId === '' && $orderId === '') {
            return null;
        }

        $payment = new Payment($paymentId);

        if ($orderId !== '') {
            $payment->setOrderId($orderId);
        }

        $method = (string) ($orderMollieFields['payment_method'] ?? '');
        $paymentMethod = $method !== '' ? PaymentMethod::tryFrom($method) : null;
        if ($paymentMethod !== null) {
            $payment->setMethod($paymentMethod);
        }

        $thirdPartyPaymentId = (string) ($orderMollieFields['third_party_payment_id'] ?? '');
        if ($thirdPartyPaymentId !== '') {
            $payment->setThirdPartyPaymentId($thirdPartyPaymentId);
        }

        $checkoutUrl = (string) ($orderMollieFields['molliePaymentUrl'] ?? '');
        if ($checkoutUrl !== '') {
            $payment->setCheckoutUrl($checkoutUrl);
        }

        $creditCardLabel = (string) ($orderMollieFields['creditCardLabel'] ?? '');
        if ($creditCardLabel !== '') {
            $payment->setCreditCardLabel($creditCardLabel);
            $payment->setCreditCardNumber((string) ($orderMollieFields['creditCardNumber'] ?? ''));
            $payment->setCreditCardHolder((string) ($orderMollieFields['creditCardHolder'] ?? ''));
        }

        $paypalPayerId = (string) ($orderMollieFields['paypalPayerId'] ?? '');
        if ($paypalPayerId !== '') {
            $payment->setPaypalPayerId($paypalPayerId);
        }

        $bankAccount = (string) ($orderMollieFields['bankAccount'] ?? '');
        if ($bankAccount !== '') {
            $payment->setBankName((string) ($orderMollieFields['bankName'] ?? ''));
            $payment->setBankAccount($bankAccount);
            $payment->setBankBic((string) ($orderMollieFields['bankBic'] ?? ''));
            $payment->setTransferReference((string) ($orderMollieFields['transferReference'] ?? ''));
            $payment->setConsumerName((string) ($orderMollieFields['consumerName'] ?? ''));
            $payment->setConsumerAccount((string) ($orderMollieFields['consumerAccount'] ?? ''));
            $payment->setConsumerBic((string) ($orderMollieFields['consumerBic'] ?? ''));
        }

        $this->orderRepository->upsert([
            [
                'id' => $order->getId(),
                'transactions' => [
                    [
                        'id' => $transaction->getId(),
                        'customFields' => [Mollie::EXTENSION => $payment->toArray()],
                    ],
                ],
            ],
        ], $context);

        return $payment;
    }
}
