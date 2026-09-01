<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Payment\ExpressComponents\CartCheckoutFinisherInterface;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeCartCheckoutFinisher implements CartCheckoutFinisherInterface
{
    private ?string $lastCartToken = null;

    public function __construct(private FinishCheckoutResponse $response = new FinishCheckoutResponse('ses_cart', 'context-token', 'order-id', '10000', 'https://shop.example/finish'))
    {
    }

    public function getLastCartToken(): string
    {
        if ($this->lastCartToken === null) {
            throw new \RuntimeException('FakeCartCheckoutFinisher was never called.');
        }

        return $this->lastCartToken;
    }

    public function wasCalled(): bool
    {
        return $this->lastCartToken !== null;
    }

    public function finish(string $cartToken, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $this->lastCartToken = $cartToken;

        return $this->response;
    }
}
