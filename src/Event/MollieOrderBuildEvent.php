<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieOrderBuildEvent
{
    /**
     * @var array<mixed>
     */
    private $metadata;

    /**
     * @var array<mixed>
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
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @param array<mixed> $orderData
     */
    public function __construct(array $orderData, OrderEntity $order, string $transactionId, SalesChannelContext $salesChannelContext)
    {
        $this->orderData = $orderData;
        $this->order = $order;
        $this->transactionId = $transactionId;
        $this->salesChannelContext = $salesChannelContext;

        $this->metadata = [];
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * @return array<mixed>
     */
    public function getOrderData(): array
    {
        return $this->orderData;
    }

    /**
     * @return array<mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
