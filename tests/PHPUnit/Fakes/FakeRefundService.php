<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\RefundServiceInterface;
use Mollie\Api\Exceptions\ApiException;
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
     * @var float
     */
    private $refundAmount;


    /**
     * @param float $refundAmount
     */
    public function __construct(float $refundAmount)
    {
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
     * @param array $refundItems
     * @param Context $context
     * @return Refund
     */
    public function refundFull(OrderEntity $order, string $description, array $refundItems, Context $context): Refund
    {
        $this->fullyRefunded = true;
        $this->refundedOrder = $order;

        return $this->buildFakeRefund();
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param float $amount
     * @param array $lineItems
     * @param Context $context
     * @return Refund
     */
    public function refundPartial(OrderEntity $order, string $description, float $amount, array $lineItems, Context $context): Refund
    {
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

    /**
     * @return Refund
     */
    private function buildFakeRefund(): Refund
    {
        $refund = new Refund(new MollieApiClient());

        $refund->amount = new \stdClass();
        $refund->amount->value = $this->refundAmount;

        return $refund;
    }

}
