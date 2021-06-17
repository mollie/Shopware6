<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Exception\MollieRefundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Order\OrderEntity;

class RefundService
{
    /** @var MollieApiFactory */
    private $apiFactory;

    /**
     * CustomFieldService constructor.
     *
     * @param MollieApiFactory $apiFactory
     */
    public function __construct(
        MollieApiFactory $apiFactory
    )
    {
        $this->apiFactory = $apiFactory;
    }

    /**
     * @param OrderEntity $order
     * @param float $amount
     * @param string|null $description
     * @return bool
     * @throws MollieRefundException
     */
    public function refund(OrderEntity $order, float $amount, ?string $description = null): bool
    {
        $payment = $this->getPaymentForOrder($order);

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
            throw new MollieRefundException(
                sprintf("Could not create a refund for order %s (Order number %s)",
                    $payment->orderId,
                    $order->getOrderNumber()
                )
            );
        }
    }

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @return bool
     * @throws MollieRefundException
     */
    public function cancel(OrderEntity $order, string $refundId): bool
    {
        $payment = $this->getPaymentForOrder($order);

        $refund = $payment->getRefund($refundId);

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
            throw new MollieRefundException(
                sprintf("Could not cancel the refund for order %s (Order number %s)",
                    $payment->orderId,
                    $order->getOrderNumber()
                )
            );
        }
    }

    /**
     * @param OrderEntity $order
     * @return array
     * @throws MollieRefundException
     */
    public function getRefunds(OrderEntity $order): array
    {
        $payment = $this->getPaymentForOrder($order);

        try {
            $refunds = $payment->refunds();
        } catch (ApiException $e) {
            throw new MollieRefundException(
                sprintf("Could not fetch refunds for order %s (Order number %s)",
                    $payment->orderId,
                    $order->getOrderNumber()
                )
            );
        }

        // Apparently Refund::amount and Refund::settlementAmount don't json encode very well, resulting in an empty
        // array, so we build an array manually.
        return array_map(function ($refund) {
            $amount = null;
            if(!is_null($refund->amount)) {
                $amount = [
                    'value' => $refund->amount->value,
                    'currency' => $refund->amount->currency,
                ];
            }

            $settlementAmount = null;
            if(!is_null($refund->settlementAmount)) {
                $settlementAmount = [
                    'value' => $refund->settlementAmount->value,
                    'currency' => $refund->settlementAmount->currency,
                ];
            }

            /** @var Refund $refund */
            return [
                'id' => $refund->id,
                'orderId' => $refund->orderId,
                'paymentId' => $refund->paymentId,
                'amount' => $amount,
                'settlementAmount' => $settlementAmount,
                'description' => $refund->description,
                'createdAt' => $refund->createdAt,
                'status' => $refund->status,
                'isFailed' => $refund->isFailed(),
                'isPending' => $refund->isPending(),
                'isProcessing' => $refund->isProcessing(),
                'isQueued' => $refund->isQueued(),
                'isTransferred' => $refund->isTransferred(),
            ];
        }, $refunds->getArrayCopy());
    }

    /**
     * @param OrderEntity $order
     * @return float
     * @throws MollieRefundException
     */
    public function getRemainingAmount(OrderEntity $order): float
    {
        return $this->getPaymentForOrder($order)->getAmountRemaining();
    }

    /**
     * @param OrderEntity $order
     * @return float
     * @throws MollieRefundException
     */
    public function getRefundedAmount(OrderEntity $order): float
    {
        return $this->getPaymentForOrder($order)->getAmountRefunded();
    }

    /**
     * @param OrderEntity $order
     * @return Payment
     * @throws MollieRefundException
     */
    private function getPaymentForOrder(OrderEntity $order): Payment
    {
        $apiClient = $this->getApiClientForOrder($order);

        if (!($apiClient instanceof MollieApiClient)) {
            throw new MollieRefundException("Could not create a Mollie Api Client");
        }

        $mollieOrderId = $order->getCustomFields()['mollie_payments']['order_id'] ?? '';

        if (empty($mollieOrderId)) {
            throw new MollieRefundException(
                sprintf('Could not find a mollie order id for order %s',
                    $order->getOrderNumber()
                )
            );
        }

        try {
            $mollieOrder = $apiClient->orders->get($mollieOrderId, ["embed" => "payments"]);
        } catch (ApiException $e) {
            throw new MollieRefundException(
                sprintf('Could not find the mollie order for id %s (Order number %s)',
                    $mollieOrderId,
                    $order->getOrderNumber()
                )
            );
        }

        // Filter for paid/authorized payments only.
        $paidPayments = array_filter($mollieOrder->payments()->getArrayCopy(), function ($payment) {
            return in_array($payment->status, [PaymentStatus::STATUS_PAID, PaymentStatus::STATUS_AUTHORIZED]);
        });

        if (count($paidPayments) === 0) {
            throw new MollieRefundException(
                sprintf(
                    "There is no payment to issue a refund on for order %s (Order number %s)",
                    $mollieOrderId,
                    $order->getOrderNumber()
                )
            );
        }

        // Return first found paid/authorized payment.
        return $paidPayments[0];
    }

    /**
     * @param OrderEntity $order
     * @return MollieApiClient
     */
    private function getApiClientForOrder(OrderEntity $order): ?MollieApiClient
    {
        try {
            return $this->apiFactory->getClient($order->getSalesChannelId());
        } catch (IncompatiblePlatform $e) {
            return null;
        }
    }
}
