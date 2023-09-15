<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Exception\CouldNotCancelMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieRefundsException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\Item\RefundItemType;
use Kiener\MolliePayments\Service\Refund\Mollie\RefundMetadata;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;

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
     * @var MollieGatewayInterface
     */
    private $gwMollie;


    /**
     * @param Order $mollie
     * @param OrderService $orders
     * @param RefundHydrator $refundHydrator
     * @param MollieGatewayInterface $gwMollie
     */
    public function __construct(Order $mollie, OrderService $orders, RefundHydrator $refundHydrator, MollieGatewayInterface $gwMollie)
    {
        $this->mollie = $mollie;
        $this->orders = $orders;
        $this->refundHydrator = $refundHydrator;
        $this->gwMollie = $gwMollie;
    }


    /**
     * @param OrderEntity $order
     * @param string $description
     * @param string $internalDescription
     * @param RefundItem[] $refundItems
     * @param Context $context
     * @throws ApiException
     * @return Refund
     */
    public function refundFull(OrderEntity $order, string $description, string $internalDescription, array $refundItems, Context $context): Refund
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
            throw new CouldNotCreateMollieRefundException($mollieOrderId, (string)$order->getOrderNumber());
        }

        return $refund;
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param string $internalDescription
     * @param float $amount
     * @param RefundItem[] $lineItems
     * @param Context $context
     * @throws ApiException
     * @return Refund
     */
    public function refundPartial(OrderEntity $order, string $description, string $internalDescription, float $amount, array $lineItems, Context $context): Refund
    {
        $metadata = new RefundMetadata(RefundItemType::PARTIAL, $lineItems);

        $payment = $this->getPayment($order);

        $refund = $payment->refund([
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => ($order->getCurrency() instanceof CurrencyEntity) ? $order->getCurrency()->getIsoCode() : '',
            ],
            'description' => $description,
            'metadata' => $metadata->toString(),
        ]);

        if (!$refund instanceof Refund) {
            throw new CouldNotCreateMollieRefundException('', (string)$order->getOrderNumber());
        }

        return $refund;
    }

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     * @throws CouldNotCancelMollieRefundException
     * @return bool
     */
    public function cancel(OrderEntity $order, string $refundId): bool
    {
        $payment = $this->getPayment($order);

        try {
            // getRefund doesn't contain all necessary @throws tags.
            // It is possible for it to throw an ApiException here if $refundId is incorrect.
            $refund = $payment->getRefund($refundId);
        } catch (ApiException $e) { // Invalid resource id
            throw new CouldNotCancelMollieRefundException('', (string)$order->getOrderNumber(), $refundId, $e);
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
            throw new CouldNotCancelMollieRefundException('', (string)$order->getOrderNumber(), $refundId, $e);
        }
    }

    /**
     * @param OrderEntity $order
     * @throws CouldNotFetchMollieOrderException
     * @throws CouldNotFetchMollieRefundsException
     * @throws PaymentNotFoundException
     * @throws CouldNotExtractMollieOrderIdException
     * @return array<mixed>
     */
    public function getRefunds(OrderEntity $order): array
    {
        $orderAttributes = new OrderAttributes($order);

        try {
            $refundsArray = [];

            $payment = $this->getPayment($order);

            /** @var Refund $refund */
            foreach ($payment->refunds()->getArrayCopy() as $refund) {
                /**
                 * TODO: for now we skip the canceled refunds since it is not implemented yet
                 * use RefundStatus canceled when available
                 */
                if ($refund->status === 'canceled') {
                    continue;
                }
                $refundsArray[] = $this->refundHydrator->hydrate($refund, $order);
            }

            return $refundsArray;
        } catch (ApiException $e) {
            throw new CouldNotFetchMollieRefundsException($orderAttributes->getMollieOrderId(), (string)$order->getOrderNumber(), $e);
        }
    }

    /**
     * @param OrderEntity $order
     * @throws PaymentNotFoundException
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @return float
     */
    public function getRemainingAmount(OrderEntity $order): float
    {
        $payment = $this->getPayment($order);

        return $payment->getAmountRemaining();
    }

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getVoucherPaidAmount(OrderEntity $order): float
    {
        $payment = $this->getPayment($order);

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
     * @throws PaymentNotFoundException
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @return float
     */
    public function getRefundedAmount(OrderEntity $order): float
    {
        $payment = $this->getPayment($order);

        return $payment->getAmountRefunded();
    }

    /**
     * @param OrderEntity $order
     * @return Payment
     */
    private function getPayment(OrderEntity $order): Payment
    {
        $orderAttributes = new OrderAttributes($order);

        if ($orderAttributes->isTypeSubscription()) {
            # subscriptions do not have a mollie order
            $this->gwMollie->switchClient($order->getSalesChannelId());

            return $this->gwMollie->getPayment($orderAttributes->getMolliePaymentId());
        }

        return $this->mollie->getCompletedPayment(
            $this->orders->getMollieOrderId($order),
            '',
            $order->getSalesChannelId()
        );
    }
}
