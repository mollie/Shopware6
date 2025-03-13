<?php
declare(strict_types=1);

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

    public function __construct(string $refundID, float $refundAmount)
    {
        $this->refundID = $refundID;
        $this->refundAmount = $refundAmount;

        $this->fullyRefunded = false;
    }

    public function isFullyRefunded(): bool
    {
        return $this->fullyRefunded;
    }

    public function getRefundedOrder(): OrderEntity
    {
        return $this->refundedOrder;
    }

    public function refundFull(OrderEntity $order, string $description, string $internalDescription, array $refundItems, Context $context): Refund
    {
        $this->fullyRefunded = true;
        $this->refundedOrder = $order;

        return $this->buildFakeRefund();
    }

    public function refundPartial(OrderEntity $order, string $description, string $internalDescription, float $amount, array $lineItems, Context $context): Refund
    {
        $this->fullyRefunded = false;
        $this->refundedOrder = $order;

        return $this->buildFakeRefund();
    }

    public function cancel(OrderEntity $order, string $refundId): bool
    {
        // TODO: Implement cancel() method.
    }

    public function getRefunds(OrderEntity $order, Context $context): array
    {
        // TODO: Implement getRefunds() method.
    }

    public function getRemainingAmount(OrderEntity $order): float
    {
        // TODO: Implement getRemainingAmount() method.
    }

    public function getVoucherPaidAmount(OrderEntity $order): float
    {
        // TODO: Implement getVoucherPaidAmount() method.
    }

    public function getRefundedAmount(OrderEntity $order): float
    {
        // TODO: Implement getRefundedAmount() method.
    }

    /**
     * @param array<mixed> $refunds
     */
    public function getPendingRefundAmount(array $refunds): float
    {
        // TODO: Implement getPendingRefundAmount() method.
    }

    private function buildFakeRefund(): Refund
    {
        $refund = new Refund(new MollieApiClient());

        $refund->id = $this->refundID;

        $refund->amount = new \stdClass();
        $refund->amount->value = $this->refundAmount;

        return $refund;
    }
}
