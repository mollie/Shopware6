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
 * Rebuilds the Mollie Payment extension from the Mollie custom fields and writes it back onto the
 * transaction so subsequent reads find it. Reads the transaction's custom fields first (they survive
 * the legacy JTL migration that could blank the order's fields) and falls back to the order, accepting
 * both camelCase (5.x) and snake_case (pre-5.0) key spellings. Returns null when neither carries a
 * recoverable Mollie id.
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
        $txFields = ($transaction->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];
        $orderFields = ($order->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];

        $paymentId = $this->firstNonEmpty(
            $txFields['id'] ?? null,
            $txFields['payment_id'] ?? null,
            $orderFields['payment_id'] ?? null,
            $orderFields['id'] ?? null,
        );
        $orderId = $this->firstNonEmpty(
            $txFields['orderId'] ?? null,
            $txFields['order_id'] ?? null,
            $orderFields['order_id'] ?? null,
            $orderFields['orderId'] ?? null,
        );

        if ($paymentId === '' && $orderId === '') {
            return null;
        }

        $payment = new Payment($paymentId);

        if ($orderId !== '') {
            $payment->setOrderId($orderId);
        }

        $method = $this->firstNonEmpty(
            $txFields['method'] ?? null,
            $orderFields['payment_method'] ?? null,
        );
        $paymentMethod = $method !== '' ? PaymentMethod::tryFrom($method) : null;
        if ($paymentMethod !== null) {
            $payment->setMethod($paymentMethod);
        }

        $thirdPartyPaymentId = $this->firstNonEmpty(
            $txFields['thirdPartyPaymentId'] ?? null,
            $txFields['third_party_payment_id'] ?? null,
            $orderFields['third_party_payment_id'] ?? null,
            $orderFields['thirdPartyPaymentId'] ?? null,
        );
        if ($thirdPartyPaymentId !== '') {
            $payment->setThirdPartyPaymentId($thirdPartyPaymentId);
        }

        $checkoutUrl = $this->firstNonEmpty(
            $txFields['checkoutUrl'] ?? null,
            $orderFields['molliePaymentUrl'] ?? null,
            $orderFields['checkoutUrl'] ?? null,
        );
        if ($checkoutUrl !== '') {
            $payment->setCheckoutUrl($checkoutUrl);
        }

        $creditCardLabel = $this->firstNonEmpty(
            $txFields['creditCardLabel'] ?? null,
            $orderFields['creditCardLabel'] ?? null,
        );
        if ($creditCardLabel !== '') {
            $payment->setCreditCardLabel($creditCardLabel);
            $payment->setCreditCardNumber($this->firstNonEmpty(
                $txFields['creditCardNumber'] ?? null,
                $orderFields['creditCardNumber'] ?? null,
            ));
            $payment->setCreditCardHolder($this->firstNonEmpty(
                $txFields['creditCardHolder'] ?? null,
                $orderFields['creditCardHolder'] ?? null,
            ));
        }

        $paypalPayerId = $this->firstNonEmpty(
            $txFields['paypalPayerId'] ?? null,
            $orderFields['paypalPayerId'] ?? null,
        );
        if ($paypalPayerId !== '') {
            $payment->setPaypalPayerId($paypalPayerId);
        }

        $bankAccount = $this->firstNonEmpty(
            $txFields['bankAccount'] ?? null,
            $orderFields['bankAccount'] ?? null,
        );
        if ($bankAccount !== '') {
            $payment->setBankName($this->firstNonEmpty($txFields['bankName'] ?? null, $orderFields['bankName'] ?? null));
            $payment->setBankAccount($bankAccount);
            $payment->setBankBic($this->firstNonEmpty($txFields['bankBic'] ?? null, $orderFields['bankBic'] ?? null));
            $payment->setTransferReference($this->firstNonEmpty($txFields['transferReference'] ?? null, $orderFields['transferReference'] ?? null));
            $payment->setConsumerName($this->firstNonEmpty($txFields['consumerName'] ?? null, $orderFields['consumerName'] ?? null));
            $payment->setConsumerAccount($this->firstNonEmpty($txFields['consumerAccount'] ?? null, $orderFields['consumerAccount'] ?? null));
            $payment->setConsumerBic($this->firstNonEmpty($txFields['consumerBic'] ?? null, $orderFields['consumerBic'] ?? null));
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

    private function firstNonEmpty(mixed ...$values): string
    {
        foreach ($values as $value) {
            $string = (string) ($value ?? '');
            if ($string !== '') {
                return $string;
            }
        }

        return '';
    }
}
