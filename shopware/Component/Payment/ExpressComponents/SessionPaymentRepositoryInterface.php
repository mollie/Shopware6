<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentEntity;
use Shopware\Core\Framework\Context;

interface SessionPaymentRepositoryInterface
{
    public function create(string $webhookId, string $sessionId, Context $context): void;

    public function findById(string $webhookId, Context $context): ?SessionPaymentEntity;

    public function assignPayment(string $webhookId, string $paymentId, Context $context): void;

    /**
     * @return ?SessionPaymentEntity the entry of the session, null when none exists
     */
    public function assignTransaction(string $sessionId, string $transactionId, Context $context): ?SessionPaymentEntity;

    /**
     * The entry once the webhook stored the payment id; without a payment id when it does not
     * arrive in time.
     */
    public function getFinishedPayment(SessionPaymentEntity $sessionPayment, Context $context): SessionPaymentEntity;

    /**
     * Whether the shopper is no longer held back waiting for the payment of this session.
     */
    public function hasShopperStoppedWaiting(string $webhookId, Context $context): bool;

    public function deleteByTransactionId(string $transactionId, Context $context): void;

    /**
     * @return int number of deleted entries
     */
    public function deleteExpired(\DateTimeImmutable $now, Context $context): int;
}
