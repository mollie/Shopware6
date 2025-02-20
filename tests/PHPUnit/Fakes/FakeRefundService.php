<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\Refund\RefundServiceInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

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
     * @var string
     */
    private $refundID;

    /**
     * @var float
     */
    private $refundAmount;


    /**
     * @param string $refundID
     * @param float $refundAmount
     */
    public function __construct(string $refundID, float $refundAmount)
    {
        $this->refundID = $refundID;
        $this->refundAmount = $refundAmount;

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
     * @param string $description
     * @param string $internalDescription
     * @param array $refundItems
     * @param Context $context
     * @return Refund
     */
    public function refundFull(OrderEntity $order, string $description, string $internalDescription, array $refundItems, Context $context): Refund
    {
        $this->fullyRefunded = true;
        $this->refundedOrder = $order;

        return $this->buildFakeRefund();
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param string $internalDescription
     * @param float $amount
     * @param array $lineItems
     * @param Context $context
     * @return Refund
     */
    public function refundPartial(OrderEntity $order, string $description, string $internalDescription, float $amount, array $lineItems, Context $context): Refund
    {
        $this->fullyRefunded = false;
        $this->refundedOrder = $order;

        return $this->buildFakeRefund();
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
    public function getRefunds(OrderEntity $order, Context $context): array
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

    /**
     * @param array<mixed> $refunds
     * @return float
     */
    public function getPendingRefundAmount(array $refunds): float
    {
        // TODO: Implement getPendingRefundAmount() method.
    }


    /**
     * @return Refund
     */
    private function buildFakeRefund(): Refund
    {
        $refund = new Refund(new MollieApiClient());

        $refund->id = $this->refundID;

        $refund->amount = new \stdClass();
        $refund->amount->value = $this->refundAmount;

        return $refund;
    }
}
