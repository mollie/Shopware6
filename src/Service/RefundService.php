<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
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
     * @param OrderService $orderService
     * @param SettingsService $settingsService
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
     * @return bool
     * @throws ApiException
     */
    public function refund(OrderEntity $order, float $amount): bool
    {
        $payment = $this->getPaymentForOrder($order);

        // We don't have a valid Mollie payment for this order, so we cant refund
        if(!($payment instanceof Payment)) {
            return false;
        }

        $refund = $payment->refund([
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => $order->getCurrency()->getIsoCode()
            ],
            'description' => "Refunded through Shopware administration. Order number {$order->getOrderNumber()}"
        ]);

        return $refund instanceof Refund;
    }

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @return bool
     * @throws ApiException
     */
    public function cancel(OrderEntity $order, string $refundId): bool
    {
        $payment = $this->getPaymentForOrder($order);

        // We don't have a valid Mollie payment for this order, so there is no refund to cancel
        if (!($payment instanceof Payment)) {
            return false;
        }

        $refund = $payment->getRefund($refundId);

        // This payment does not have a refund with $refundId, so we cannot cancel it.
        if (!($refund instanceof Refund)) {
            return false;
        }

        $refund->cancel();

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return array
     * @throws ApiException
     */
    public function getRefunds(OrderEntity $order): array
    {
        $payment = $this->getPaymentForOrder($order);

        // We don't have a valid Mollie payment for this order, so there cannot be any refunds yet.
        if (!($payment instanceof Payment)) {
            return [];
        }

        $refunds = $payment->refunds();

        // Apparently Refund::amount and Refund::settlementAmount don't json encode very well, resulting in an empty
        // array, so we build an array manually.
        return array_map(function ($refund) {
            /** @var Refund $refund */
            return [
                'id' => $refund->id,
                'orderId' => $refund->orderId,
                'paymentId' => $refund->paymentId,
                'amount' => [
                    'value' => $refund->amount->value,
                    'currency' => $refund->amount->currency,
                ],
                'settlementAmount' => [
                    'value' => $refund->settlementAmount->value,
                    'currency' => $refund->settlementAmount->currency,
                ],
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
     */
    public function getRemainingAmount(OrderEntity $order): float
    {
        $payment = $this->getPaymentForOrder($order);

        // We don't have a valid Mollie payment for this order, so nothing can be refunded.
        if (!($payment instanceof Payment)) {
            return 0;
        }

        return $payment->getAmountRemaining();
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getRefundedAmount(OrderEntity $order): float
    {
        $payment = $this->getPaymentForOrder($order);

        // We don't have a valid Mollie payment for this order, so nothing can be refunded.
        if (!($payment instanceof Payment)) {
            return 0;
        }

        return $payment->getAmountRefunded();
    }

    /**
     * @param OrderEntity $order
     * @return Payment
     */
    private function getPaymentForOrder(OrderEntity $order): ?Payment
    {
        $apiClient = $this->getApiClientForOrder($order);

        if (!($apiClient instanceof MollieApiClient)) {
            return null;
        }

        try {
            $mollieOrderId = $order->getCustomFields()[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'];
        } catch (\Throwable $e) {
            // No Mollie order id in custom fields, so it's not an order paid with Mollie.
            $mollieOrderId = null;
        }

        if (is_null($mollieOrderId)) {
            return null;
        }

        try {
            $mollieOrder = $apiClient->orders->get($mollieOrderId, ["embed" => "payments"]);
        } catch (ApiException $e) {
            return null;
        }

        if (!($mollieOrder instanceof Order)) {
            return null;
        }

        // Filter for paid/authorized payments only.
        $paidPayments = array_filter($mollieOrder->payments()->getArrayCopy(), function ($payment) {
            return in_array($payment->status, [PaymentStatus::STATUS_PAID, PaymentStatus::STATUS_AUTHORIZED]);
        });

        if (count($paidPayments) === 0) {
            return null;
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
            return $this->apiFactory->createClient($order->getSalesChannelId());
        } catch (IncompatiblePlatform $e) {
            return null;
        }
    }
}
