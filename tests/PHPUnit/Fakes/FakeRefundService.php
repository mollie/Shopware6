<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\OrderServiceInterface;
use Kiener\MolliePayments\Service\RefundServiceInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeRefundService implements RefundServiceInterface
{

    /**
     * @var bool
     */
    private $fullyRefunded;

    /**
     * @var OrderEntity
     */
    private $refundedOrder;


    /**
     *
     */
    public function __construct()
    {
        $this->fullyRefunded = false;
    }

    /**
     * @return bool
     */
    public function isFullyRefunded(): bool
    {
        return $this->fullyRefunded;
    }

    /**
     * @return OrderEntity
     */
    public function getRefundedOrder(): OrderEntity
    {
        return $this->refundedOrder;
    }


    /**
     * @param OrderEntity $order
     * @param float $amount
     * @param string|null $description
     * @return Refund
     */
    public function refund(OrderEntity $order, float $amount, ?string $description): Refund
    {
        // TODO: Implement refund() method.
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @return Refund
     */
    public function refundFullOrder(OrderEntity $order, string $description): Refund
    {
        $this->fullyRefunded = true;
        $this->refundedOrder = $order;

        return new Refund(new MollieApiClient());
    }

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @return bool
     */
    public function cancel(OrderEntity $order, string $refundId): bool
    {
        // TODO: Implement cancel() method.
    }

    /**
     * @param OrderEntity $order
     * @return array
     */
    public function getRefunds(OrderEntity $order): array
    {
        // TODO: Implement getRefunds() method.
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getRemainingAmount(OrderEntity $order): float
    {
        // TODO: Implement getRemainingAmount() method.
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getVoucherPaidAmount(OrderEntity $order): float
    {
        // TODO: Implement getVoucherPaidAmount() method.
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getRefundedAmount(OrderEntity $order): float
    {
        // TODO: Implement getRefundedAmount() method.
    }

}
