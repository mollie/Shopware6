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
     * @return bool false when no entry exists for the session
     */
    public function assignTransaction(string $sessionId, string $transactionId, Context $context): bool;

    public function deleteByTransactionId(string $transactionId, Context $context): void;

    /**
     * @return int number of deleted entries
     */
    public function deleteExpired(\DateTimeImmutable $now, Context $context): int;
}
