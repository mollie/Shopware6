<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Shopware\Core\Framework\Context;

final class FakeWebhookRoute extends AbstractWebhookRoute
{
    /** @var list<string> */
    private array $notifiedTransactionIds = [];

    /** @var list<string> */
    private array $failingTransactionIds = [];

    public function getDecorated(): self
    {
        throw new \RuntimeException('Not decorated');
    }

    public function addFailingTransactionId(string $transactionId): void
    {
        $this->failingTransactionIds[] = $transactionId;
    }

    public function notify(string $transactionId, Context $context): WebhookResponse
    {
        if (in_array($transactionId, $this->failingTransactionIds, true)) {
            throw new \RuntimeException('Mollie API unavailable');
        }

        $this->notifiedTransactionIds[] = $transactionId;

        return new WebhookResponse(new Payment($transactionId));
    }

    /** @return list<string> */
    public function getNotifiedTransactionIds(): array
    {
        return $this->notifiedTransactionIds;
    }
}
