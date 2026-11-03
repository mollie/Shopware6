<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPayment;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepositoryInterface;

final class FakeSessionPaymentRepository implements SessionPaymentRepositoryInterface
{
    /** @var array<string, SessionPayment> */
    private array $entries = [];

    /** @var array<string> */
    private array $deletedTransactionIds = [];

    public function __construct(SessionPayment ...$entries)
    {
        foreach ($entries as $entry) {
            $this->entries[$entry->getId()] = $entry;
        }
    }

    public function create(string $webhookId, string $sessionId): void
    {
        $this->entries[$webhookId] = new SessionPayment($webhookId, $sessionId, null, null);
    }

    public function findById(string $webhookId): ?SessionPayment
    {
        return $this->entries[$webhookId] ?? null;
    }

    public function assignPayment(string $webhookId, string $paymentId): void
    {
        $entry = $this->entries[$webhookId];
        $this->entries[$webhookId] = new SessionPayment($webhookId, $entry->getSessionId(), $paymentId, $entry->getTransactionId());
    }

    public function assignTransaction(string $sessionId, string $transactionId): bool
    {
        foreach ($this->entries as $webhookId => $entry) {
            if ($entry->getSessionId() !== $sessionId) {
                continue;
            }

            $this->entries[$webhookId] = new SessionPayment($webhookId, $sessionId, $entry->getPaymentId(), $transactionId);

            return true;
        }

        return false;
    }

    public function deleteByTransactionId(string $transactionId): void
    {
        $this->deletedTransactionIds[] = $transactionId;
    }

    /**
     * @return array<string, SessionPayment>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function findBySessionId(string $sessionId): ?SessionPayment
    {
        foreach ($this->entries as $entry) {
            if ($entry->getSessionId() === $sessionId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<string>
     */
    public function getDeletedTransactionIds(): array
    {
        return $this->deletedTransactionIds;
    }
}
