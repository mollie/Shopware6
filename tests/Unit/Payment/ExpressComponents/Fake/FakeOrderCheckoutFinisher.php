<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Payment\ExpressComponents\OrderCheckoutFinisherInterface;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeOrderCheckoutFinisher implements OrderCheckoutFinisherInterface
{
    private ?string $lastOrderId = null;

    public function __construct(private FinishCheckoutResponse $response = new FinishCheckoutResponse('ses_order', 'context-token', 'order-id', '10000', 'https://shop.example/finish'))
    {
    }

    public function getLastOrderId(): string
    {
        if ($this->lastOrderId === null) {
            throw new \RuntimeException('FakeOrderCheckoutFinisher was never called.');
        }

        return $this->lastOrderId;
    }

    public function wasCalled(): bool
    {
        return $this->lastOrderId !== null;
    }

    public function finish(string $orderId, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $this->lastOrderId = $orderId;

        return $this->response;
    }
}
