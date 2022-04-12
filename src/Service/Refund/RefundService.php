<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Exception\CouldNotCancelMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieRefundsException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\Item\RefundItemType;
use Kiener\MolliePayments\Service\Refund\Mollie\RefundMetadata;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RefundService implements RefundServiceInterface
{

    /**
     * @var Order
     */
    private $mollie;

    /**
     * @var OrderService
     */
    private $orders;

    /**
     * @var RefundHydrator
     */
    private $refundHydrator;


    /**
     * @param Order $mollie
     * @param OrderService $orders
     * @param RefundHydrator $refundHydrator
     */
    public function __construct(Order $mollie, OrderService $orders, RefundHydrator $refundHydrator)
    {
        $this->mollie = $mollie;
        $this->orders = $orders;
        $this->refundHydrator = $refundHydrator;
    }


    /**
     * @param OrderEntity $order
     * @param string $description
     * @param RefundItem[] $refundItems
     * @param Context $context
     * @return Refund
     * @throws ApiException
     */
    public function refundFull(OrderEntity $order, string $description, array $refundItems, Context $context): Refund
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);
        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, $order->getSalesChannelId());


        $metadata = new RefundMetadata(RefundItemType::FULL, $refundItems);

        $params = [
            'description' => $description,
            'metadata' => $metadata->toString(),
        ];

        if (count($refundItems) > 0) {

            $lines = [];

            foreach ($refundItems as $item) {
                # quantities of 0 do not work with the Mollie API
                if ($item->getQuantity() <= 0) {
                    continue;
                }

                $lines[] = [
                    'id' => $item->getMollieLineId(),
                    'quantity' => $item->getQuantity(),
                ];
            }

            $params['lines'] = $lines;
        }

        # REFUND WITH MOLLIE
        # ---------------------------------------------------------------------------------------------
        $refund = $mollieOrder->refund($params);

        if (!$refund instanceof Refund) {
            throw new CouldNotCreateMollieRefundException($mollieOrderId, $order->getOrderNumber());
        }

        return $refund;
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param float $amount
     * @param RefundItem[] $lineItems
     * @param Context $context
     * @return Refund
     * @throws ApiException
     */
    public function refundPartial(OrderEntity $order, string $description, float $amount, array $lineItems, Context $context): Refund
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);

        $metadata = new RefundMetadata(RefundItemType::PARTIAL, $lineItems);

        $payment = $this->mollie->getCompletedPayment($mollieOrderId, $order->getSalesChannelId());

        $refund = $payment->refund([
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => $order->getCurrency()->getIsoCode()
            ],
            'description' => $description,
            'metadata' => $metadata->toString(),
        ]);

        if (!$refund instanceof Refund) {
            throw new CouldNotCreateMollieRefundException($mollieOrderId, $order->getOrderNumber());
        }

        return $refund;
    }

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @return bool
     * @throws CouldNotCancelMollieRefundException
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     */
    public function cancel(OrderEntity $order, string $refundId): bool
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);

        $payment = $this->mollie->getCompletedPayment($mollieOrderId, $order->getSalesChannelId());

        try {
            // getRefund doesn't contain all necessary @throws tags.
            // It is possible for it to throw an ApiException here if $refundId is incorrect.
            $refund = $payment->getRefund($refundId);
        } catch (ApiException $e) { // Invalid resource id
            throw new CouldNotCancelMollieRefundException($mollieOrderId, $order->getOrderNumber(), $refundId, $e);
        }

        // This payment does not have a refund with $refundId, so we cannot cancel it.
        if (!($refund instanceof Refund)) {
            return false;
        }

        // Refunds can only be cancelled when they're still queued or pending.
        if (!$refund->isQueued() && !$refund->isPending()) {
            return false;
        }

        try {
            $refund->cancel();
            return true;
        } catch (ApiException $e) {
            throw new CouldNotCancelMollieRefundException($mollieOrderId, $order->getOrderNumber(), $refundId, $e);
        }
    }

    /**
     * @param OrderEntity $order
     * @return array<mixed>
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws CouldNotFetchMollieRefundsException
     * @throws PaymentNotFoundException
     */
    public function getRefunds(OrderEntity $order): array
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);

        $payment = $this->mollie->getCompletedPayment($mollieOrderId, $order->getSalesChannelId());

        try {
            $refundsArray = [];

            foreach ($payment->refunds()->getArrayCopy() as $refund) {
                $refundsArray[] = $this->refundHydrator->hydrate($refund);
            }

            return $refundsArray;
        } catch (ApiException $e) {
            throw new CouldNotFetchMollieRefundsException($mollieOrderId, $order->getOrderNumber(), $e);
        }
    }

    /**
     * @param OrderEntity $order
     * @return float
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     */
    public function getRemainingAmount(OrderEntity $order): float
    {
        $payment = $this->mollie->getCompletedPayment(
            $this->orders->getMollieOrderId($order),
            $order->getSalesChannelId()
        );

        return $payment->getAmountRemaining();
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getVoucherPaidAmount(OrderEntity $order): float
    {
        $payment = $this->mollie->getCompletedPayment(
            $this->orders->getMollieOrderId($order),
            $order->getSalesChannelId()
        );

        if ($payment->details === null) {
            return 0;
        }

        if (!property_exists($payment->details, 'vouchers')) {
            return 0;
        }

        $voucherAmount = 0;

        /** @var \stdClass $voucher */
        foreach ($payment->details->vouchers as $voucher) {
            $voucherAmount += (float)$voucher->amount->value;
        }

        return $voucherAmount;
    }

    /**
     * @param OrderEntity $order
     * @return float
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     */
    public function getRefundedAmount(OrderEntity $order): float
    {
        $payment = $this->mollie->getCompletedPayment(
            $this->orders->getMollieOrderId($order),
            $order->getSalesChannelId()
        );

        return $payment->getAmountRefunded();
    }

}
