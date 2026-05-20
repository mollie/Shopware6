<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Shopware\Core\Framework\Context;

final class FakePaymentWebhookRoute extends AbstractWebhookRoute
{
    /** @var list<array{transactionId:string}> */
    private array $calls = [];

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{transactionId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getDecorated(): AbstractWebhookRoute
    {
        throw new \LogicException('FakePaymentWebhookRoute::getDecorated not implemented');
    }

    public function notify(string $transactionId, Context $context): WebhookResponse
    {
        $this->calls[] = ['transactionId' => $transactionId];

        return new WebhookResponse(new Payment('payment-from-fake-payment-webhook'));
    }
}
