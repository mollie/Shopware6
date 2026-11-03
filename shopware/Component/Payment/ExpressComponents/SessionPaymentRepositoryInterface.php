<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

interface SessionPaymentRepositoryInterface
{
    public function create(string $webhookId, string $sessionId): void;

    public function findById(string $webhookId): ?SessionPayment;

    public function assignPayment(string $webhookId, string $paymentId): void;

    /**
     * @return bool false when no entry exists for the session
     */
    public function assignTransaction(string $sessionId, string $transactionId): bool;

    public function deleteByTransactionId(string $transactionId): void;
}
