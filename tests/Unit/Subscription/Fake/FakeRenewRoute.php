<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Subscription\Route\AbstractRenewRoute;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class FakeRenewRoute extends AbstractRenewRoute
{
    /** @var list<array{subscriptionId:string}> */
    private array $calls = [];

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{subscriptionId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getDecorated(): AbstractRenewRoute
    {
        throw new \LogicException('FakeRenewRoute::getDecorated not implemented');
    }

    public function renew(string $subscriptionId, Request $request, Context $context): WebhookResponse
    {
        $this->calls[] = ['subscriptionId' => $subscriptionId];

        return new WebhookResponse(new Payment('payment-from-fake-renew-route'));
    }
}
