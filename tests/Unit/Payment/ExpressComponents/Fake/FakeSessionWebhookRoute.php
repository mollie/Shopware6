<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractSessionWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class FakeSessionWebhookRoute extends AbstractSessionWebhookRoute
{
    /** @var list<array{webhookId: string, paymentId: string}> */
    private array $calls = [];

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function getDecorated(): AbstractSessionWebhookRoute
    {
        throw new \RuntimeException('FakeSessionWebhookRoute is not decorated.');
    }

    public function notify(string $webhookId, Request $request, Context $context): WebhookResponse
    {
        $paymentId = (string) $request->request->get('id');
        $this->calls[] = ['webhookId' => $webhookId, 'paymentId' => $paymentId];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new WebhookResponse(new Payment($paymentId));
    }

    /**
     * @return list<array{webhookId: string, paymentId: string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
