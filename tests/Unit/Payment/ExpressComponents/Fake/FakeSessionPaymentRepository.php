<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentEntity;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepositoryInterface;
use Shopware\Core\Framework\Context;

final class FakeSessionPaymentRepository implements SessionPaymentRepositoryInterface
{
    /** @var array<string, SessionPaymentEntity> */
    private array $entries = [];

    /** @var array<string> */
    private array $deletedTransactionIds = [];

    private int $deleteExpiredCalls = 0;

    private ?string $paymentIdArrivingWhileWaiting = null;

    private bool $waitedInVain = false;

    private bool $shopperStoppedWaiting = false;

    public function __construct(SessionPaymentEntity ...$entries)
    {
        foreach ($entries as $entry) {
            $this->entries[$entry->getId()] = $entry;
        }
    }

    public static function entry(string $webhookId, string $sessionId, ?string $paymentId = null, ?string $transactionId = null): SessionPaymentEntity
    {
        $entry = new SessionPaymentEntity();
        $entry->setId($webhookId);
        $entry->setSessionId($sessionId);
        $entry->setPaymentId($paymentId);
        $entry->setTransactionId($transactionId);

        return $entry;
    }

    public function create(string $webhookId, string $sessionId, Context $context): void
    {
        $this->entries[$webhookId] = self::entry($webhookId, $sessionId);
    }

    public function findById(string $webhookId, Context $context): ?SessionPaymentEntity
    {
        $entry = $this->entries[$webhookId] ?? null;
        if ($entry === null) {
            return null;
        }

        // a copy, like a fresh load from the database
        return self::entry($entry->getId(), $entry->getSessionId(), $entry->getPaymentId(), $entry->getTransactionId());
    }

    public function assignPayment(string $webhookId, string $paymentId, Context $context): void
    {
        $this->entries[$webhookId]->setPaymentId($paymentId);
    }

    public function assignTransaction(string $sessionId, string $transactionId, Context $context): ?SessionPaymentEntity
    {
        $entry = $this->findBySessionId($sessionId);
        if ($entry === null) {
            return null;
        }

        $entry->setTransactionId($transactionId);

        return self::entry($entry->getId(), $entry->getSessionId(), $entry->getPaymentId(), $transactionId);
    }

    /**
     * Without a payment id arriving the wait runs out, as when the webhook does not come in time.
     */
    public function getFinishedPayment(SessionPaymentEntity $sessionPayment, Context $context): SessionPaymentEntity
    {
        if ((string) $sessionPayment->getPaymentId() !== '') {
            return $sessionPayment;
        }

        if ($this->paymentIdArrivingWhileWaiting === null) {
            $this->waitedInVain = true;

            return $sessionPayment;
        }

        $this->assignPayment($sessionPayment->getId(), $this->paymentIdArrivingWhileWaiting, $context);

        return $this->findById($sessionPayment->getId(), $context) ?? $sessionPayment;
    }

    public function hasShopperStoppedWaiting(string $webhookId, Context $context): bool
    {
        return $this->shopperStoppedWaiting;
    }

    public function letPaymentIdArriveWhileWaiting(string $paymentId): void
    {
        $this->paymentIdArrivingWhileWaiting = $paymentId;
    }

    public function letShopperStopWaiting(): void
    {
        $this->shopperStoppedWaiting = true;
    }

    public function hasWaitedInVain(): bool
    {
        return $this->waitedInVain;
    }

    public function deleteByTransactionId(string $transactionId, Context $context): void
    {
        $this->deletedTransactionIds[] = $transactionId;
    }

    public function deleteExpired(\DateTimeImmutable $now, Context $context): int
    {
        ++$this->deleteExpiredCalls;

        return 0;
    }

    public function getDeleteExpiredCalls(): int
    {
        return $this->deleteExpiredCalls;
    }

    /**
     * @return array<string, SessionPaymentEntity>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function findBySessionId(string $sessionId): ?SessionPaymentEntity
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
