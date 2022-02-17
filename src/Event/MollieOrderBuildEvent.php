<?php

namespace Kiener\MolliePayments\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieOrderBuildEvent
{
    /**
     * @var array
     */
    private $orderData;

    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var string
     */
    private $transactionId;

    /**
     * @var string
     */
    private $paymentMethod;

    /**
     * @var string
     */
    private $returnUrl;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var PaymentHandler|null
     */
    private $handler;

    /**
     * @var array
     */
    private $paymentData;

    public function __construct(array $orderData, OrderEntity $order, string $transactionId, string $paymentMethod, string $returnUrl, SalesChannelContext $salesChannelContext, ?PaymentHandler $handler, array $paymentData = [])
    {
        $this->orderData = $orderData;
        $this->order = $order;
        $this->transactionId = $transactionId;
        $this->paymentMethod = $paymentMethod;
        $this->returnUrl = $returnUrl;
        $this->salesChannelContext = $salesChannelContext;
        $this->handler = $handler;
        $this->paymentData = $paymentData;

        dd($this);
    }

    public function getOrderData(): array
    {
        return $this->orderData;
    }

    public function setOrderData(array $orderData): void
    {
        $this->orderData = $orderData;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getHandler(): ?PaymentHandler
    {
        return $this->handler;
    }

    public function getPaymentData(): array
    {
        return $this->paymentData;
    }
}
