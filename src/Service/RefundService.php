<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Exception\CouldNotCancelMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieRefundsException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RefundService
{
    /** @var Order */
    private $mollieOrderApi;

    /** @var OrderService */
    private $orderService;

    /** @var RefundHydrator */
    private $refundHydrator;

    /**
     * CustomFieldService constructor.
     *
     * @param Order $mollieOrderApi
     * @param OrderService $orderService
     * @param RefundHydrator $refundHydrator
     */
    public function __construct(
        Order          $mollieOrderApi,
        OrderService   $orderService,
        RefundHydrator $refundHydrator
    )
    {
        $this->mollieOrderApi = $mollieOrderApi;
        $this->orderService = $orderService;
        $this->refundHydrator = $refundHydrator;
    }

    /**
     * @param OrderEntity $order
     * @param float $amount
     * @param string|null $description
     * @return bool
     * @throws CouldNotCreateMollieRefundException
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     */
    public function refund(OrderEntity $order, float $amount, ?string $description, Context $context): bool
    {
        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $payment = $this->mollieOrderApi->getCompletedPayment($mollieOrderId, $order->getSalesChannelId(), $context);

        try {
            $refund = $payment->refund([
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => $order->getCurrency()->getIsoCode()
                ],
                'description' => $description ?? sprintf("Refunded through Shopware administration. Order number %s",
                        $order->getOrderNumber())
            ]);

            return $refund instanceof Refund;
        } catch (ApiException $e) {
            throw new CouldNotCreateMollieRefundException($mollieOrderId, $order->getOrderNumber(), $e);
        }
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
    public function cancel(OrderEntity $order, string $refundId, Context $context): bool
    {
        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $payment = $this->mollieOrderApi->getCompletedPayment($mollieOrderId, $order->getSalesChannelId(), $context);

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
     * @return array
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws CouldNotFetchMollieRefundsException
     * @throws PaymentNotFoundException
     */
    public function getRefunds(OrderEntity $order, Context $context): array
    {
        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $payment = $this->mollieOrderApi->getCompletedPayment($mollieOrderId, $order->getSalesChannelId(), $context);

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
    public function getRemainingAmount(OrderEntity $order, Context $context): float
    {
        $payment = $this->mollieOrderApi->getCompletedPayment(
            $this->orderService->getMollieOrderId($order),
            $order->getSalesChannelId(),
            $context
        );

        return $payment->getAmountRemaining();
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return float
     */
    public function getVoucherPaidAmount(OrderEntity $order, Context $context): float
    {
        $payment = $this->mollieOrderApi->getCompletedPayment(
            $this->orderService->getMollieOrderId($order),
            $order->getSalesChannelId(),
            $context
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
    public function getRefundedAmount(OrderEntity $order, Context $context): float
    {
        $payment = $this->mollieOrderApi->getCompletedPayment(
            $this->orderService->getMollieOrderId($order),
            $order->getSalesChannelId(),
            $context
        );

        return $payment->getAmountRefunded();
    }
}
