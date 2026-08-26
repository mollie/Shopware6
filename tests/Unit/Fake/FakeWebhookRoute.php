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

    /** @var array<string, \Throwable> */
    private array $failures = [];

    public function getDecorated(): self
    {
        throw new \RuntimeException('Not decorated');
    }

    /**
     * Without an explicit failure the route fails the way an unreachable API does. Pass one to
     * cover a caller that distinguishes exception types, e.g. a Shopware HTTP exception with a
     * status code of its own.
     */
    public function addFailingTransactionId(string $transactionId, ?\Throwable $failure = null): void
    {
        $this->failingTransactionIds[] = $transactionId;

        if ($failure !== null) {
            $this->failures[$transactionId] = $failure;
        }
    }

    public function notify(string $transactionId, Context $context): WebhookResponse
    {
        if (in_array($transactionId, $this->failingTransactionIds, true)) {
            throw $this->failures[$transactionId] ?? new \RuntimeException('Mollie API unavailable');
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
