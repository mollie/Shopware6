<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FailureMode;

use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class PaymentPageFailedEvent
{
    public function __construct(private string $transactionId, private OrderEntity $order,private Payment $payment,private SalesChannelContext $salesChannelContext)
    {
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getRedirectUrl(): string
    {
        return $this->payment->getCheckoutUrl();
    }
}
