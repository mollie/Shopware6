<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class FakeRefundManager implements RefundManagerInterface
{
    /**
     * @var OrderEntity
     */
    private $refundedOrder;

    /**
     * @var RefundRequest
     */
    private $refundRequest;

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
    }

    /**
     * @return RefundRequest
     */
    public function getRefundRequest(): RefundRequest
    {
        return $this->refundRequest;
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
     * @param Context $context
     * @return RefundData
     */
    public function getData(OrderEntity $order, Context $context): RefundData
    {
    }

    /**
     * @param OrderEntity $order
     * @param RefundRequest $request
     * @param Context $context
     * @return Refund
     */
    public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund
    {
        $this->refundRequest = $request;
        $this->refundedOrder = $order;

        return $this->buildFakeRefund();
    }

    /**
     * @param string $orderId
     * @param string $refundId
     * @param Context $context
     * @return bool
     */
    public function cancelRefund(string $orderId, string $refundId, Context $context): bool
    {
        return true;
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

    public function cancelAllOrderRefunds(OrderEntity $order, Context $context): bool
    {
        // TODO: Implement cancelAllOrderRefunds() method.
    }
}
