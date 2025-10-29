<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

final class UpdateStatusResult
{
    private array $updateTransactionIds = [];

    public function getUpdated(): int
    {
        return count($this->updateTransactionIds);
    }

    public function addUpdateId(string $transactionId): void
    {
        $this->updateTransactionIds[] = $transactionId;
    }
}
