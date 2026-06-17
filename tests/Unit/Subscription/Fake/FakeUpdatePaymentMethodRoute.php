<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\Route\AbstractUpdatePaymentMethodRoute;
use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodConfirmedResponse;
use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodResponse;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeUpdatePaymentMethodRoute extends AbstractUpdatePaymentMethodRoute
{
    /** @var list<array{type:string,subscriptionId:string}> */
    private array $calls = [];

    private ?\Throwable $exception = null;

    public function __construct(private string $checkoutUrl = 'https://mollie.test/checkout')
    {
    }

    public function setException(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * @return list<array{type:string,subscriptionId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    public function getDecorated(): AbstractUpdatePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function start(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdatePaymentMethodResponse
    {
        $this->calls[] = ['type' => 'start', 'subscriptionId' => $subscriptionId];

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return new UpdatePaymentMethodResponse($subscriptionId, $this->checkoutUrl);
    }

    public function confirm(string $subscriptionId, SalesChannelContext $context): UpdatePaymentMethodConfirmedResponse
    {
        $this->calls[] = ['type' => 'confirm', 'subscriptionId' => $subscriptionId];

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return new UpdatePaymentMethodConfirmedResponse($subscriptionId);
    }
}
