<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

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
    public const CF_REFUNDED_AMOUNT = 'refundedAmount';
    public const CF_REFUNDED_QUANTITY = 'refundedQuantity';

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var OrderService */
    private $orderService;

    /** @var SettingsService */
    private $settingsService;

    /**
     * CustomFieldService constructor.
     *
     * @param MollieApiFactory $apiFactory
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     */
    public function __construct(
        MollieApiFactory $apiFactory,
        OrderService $orderService,
        SettingsService $settingsService
    )
    {
        $this->apiFactory = $apiFactory;
        $this->orderService = $orderService;
        $this->settingsService = $settingsService;
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

        if (is_null($payment)) {
            return false;
        }

        $refund = $payment->getRefund($refundId);

        if(is_null($refund)) {
            return false;
        }

        $refund->cancel();

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return array|null
     * @throws ApiException
     */
    public function getRefunds(OrderEntity $order): ?array
    {
        $payment = $this->getPaymentForOrder($order);

        if (is_null($payment)) {
            return null;
        }

        $refunds = $payment->refunds();

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
    public function getRefundableAmount(OrderEntity $order): float
    {
        $payment = $this->getPaymentForOrder($order);

        if (is_null($payment)) {
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

        if (is_null($payment)) {
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

        if (is_null($apiClient)) {
            return null;
        }

        try {
            $mollieOrderId = $order->getCustomFields()[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'];
        } catch (\Throwable $e) {
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

        if (is_null($mollieOrder)) {
            return null;
        }

        $paidPayments = array_filter($mollieOrder->payments()->getArrayCopy(), function ($payment) {
            return in_array($payment->status, [PaymentStatus::STATUS_PAID, PaymentStatus::STATUS_AUTHORIZED]);
        });

        if (count($paidPayments) === 0) {
            return null;
        }

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
