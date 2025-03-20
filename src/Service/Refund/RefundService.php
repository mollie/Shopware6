<?php
declare(strict_types=1);

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
     * @var CompositionMigrationServiceInterface
     */
    private $compositionRepairService;

    public function __construct(Order $mollie, OrderService $orders, RefundHydrator $refundHydrator, MollieGatewayInterface $gwMollie, CompositionMigrationServiceInterface $compositionRepairService)
    {
        $this->mollie = $mollie;
        $this->orders = $orders;
        $this->refundHydrator = $refundHydrator;
        $this->gwMollie = $gwMollie;
        $this->compositionRepairService = $compositionRepairService;
    }

    /**
     * @param RefundItem[] $refundItems
     *
     * @throws ApiException
     */
    public function refundFull(OrderEntity $order, string $description, string $internalDescription, array $refundItems, Context $context): Refund
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);

        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, $order->getSalesChannelId());

        $remainingAmount = $this->getRemainingAmount($order);

        if ($remainingAmount <= 0) {
            throw new \Exception('No remaining amount to refund for order ' . $order->getOrderNumber());
        }

        // now check if our remaining amount is not the total amount already
        // because then we need to do a partial refund if its a "full refund of the REST of the order".
        $allRefunds = $this->getRefunds($order, $context);

        $refundedAmount = $this->getRefundedAmount($order);
        $pendingRefundAmount = $this->getPendingRefundAmount($allRefunds);

        // let's just see what has been basically processed or triggered
        $processedAmount = $refundedAmount + $pendingRefundAmount;

        // if we have already refunded something, but still want to refund the full rest of the order
        // then we just do a partial refund with the difference
        if ($processedAmount > 0) {
            // do a partial refund (but always without items, because we never really know)
            return $this->refundPartial($order, $description, $internalDescription, $remainingAmount, [], $context);
        }

        $metadata = new RefundMetadata(RefundItemType::FULL, $refundItems);

        $params = [
            'description' => $description,
            'metadata' => $metadata->toMolliePayload(),
        ];

        if (count($refundItems) > 0) {
            $lines = [];

            foreach ($refundItems as $item) {
                // quantities of 0 do not work with the Mollie API
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

        // REFUND WITH MOLLIE
        // ---------------------------------------------------------------------------------------------
        /** @var ?Refund $refund */
        $refund = $mollieOrder->refund($params);

        if (! $refund instanceof Refund) {
            throw new CouldNotCreateMollieRefundException($mollieOrderId, (string) $order->getOrderNumber());
        }

        return $refund;
    }

    /**
     * @param RefundItem[] $lineItems
     *
     * @throws ApiException
     */
    public function refundPartial(OrderEntity $order, string $description, string $internalDescription, float $amount, array $lineItems, Context $context): Refund
    {
        $metadata = new RefundMetadata(RefundItemType::PARTIAL, $lineItems);

        $payment = $this->getPayment($order);

        /** @var ?Refund $refund */
        $refund = $payment->refund([
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => ($order->getCurrency() instanceof CurrencyEntity) ? $order->getCurrency()->getIsoCode() : '',
            ],
            'description' => $description,
            'metadata' => $metadata->toMolliePayload(),
        ]);

        if (! $refund instanceof Refund) {
            throw new CouldNotCreateMollieRefundException('', (string) $order->getOrderNumber());
        }

        return $refund;
    }

    /**
     * @throws PaymentNotFoundException
     * @throws CouldNotCancelMollieRefundException
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     */
    public function cancel(OrderEntity $order, string $refundId): bool
    {
        $payment = $this->getPayment($order);

        try {
            // getRefund doesn't contain all necessary @throws tags.
            // It is possible for it to throw an ApiException here if $refundId is incorrect.
            /** @var ?Refund $refund */
            $refund = $payment->getRefund($refundId);
        } catch (ApiException $e) { // Invalid resource id
            throw new CouldNotCancelMollieRefundException('', (string) $order->getOrderNumber(), $refundId, $e);
        }

        // This payment does not have a refund with $refundId, so we cannot cancel it.
        if (! ($refund instanceof Refund)) {
            return false;
        }

        // Refunds can only be cancelled when they're still queued or pending.
        if (! $refund->isQueued() && ! $refund->isPending()) {
            return false;
        }

        try {
            $refund->cancel();

            return true;
        } catch (ApiException $e) {
            throw new CouldNotCancelMollieRefundException('', (string) $order->getOrderNumber(), $refundId, $e);
        }
    }

    /**
     * @throws PaymentNotFoundException
     * @throws CouldNotExtractMollieOrderIdException
     * @throws CouldNotFetchMollieOrderException
     * @throws CouldNotFetchMollieRefundsException
     *
     * @return array<mixed>
     */
    public function getRefunds(OrderEntity $order, Context $context): array
    {
        $orderAttributes = new OrderAttributes($order);

        try {
            $refundsArray = [];

            $payment = $this->getPayment($order);

            /** @var Refund $refund */
            foreach ($payment->refunds()->getArrayCopy() as $refund) {
                /*
                 * TODO: for now we skip the canceled refunds since it is not implemented yet
                 * use RefundStatus canceled when available
                 */
                if ($refund->status === 'canceled') {
                    continue;
                }

                // if we have a metadata entry, then make sure to
                // migrate those compositions (if existing) to our database storage (for legacy refunds)
                /** @phpstan-ignore-next-line  */
                if (property_exists($refund, 'metadata')) {
                    /** @var \stdClass|string $metadata */
                    $metadata = $refund->metadata;
                    if (is_string($metadata)) {
                        $order = $this->compositionRepairService->updateRefundItems($refund, $order, $context);
                    }
                }

                $refundsArray[] = $this->refundHydrator->hydrate($refund, $order);
            }

            return $refundsArray;
        } catch (ApiException $e) {
            throw new CouldNotFetchMollieRefundsException($orderAttributes->getMollieOrderId(), (string) $order->getOrderNumber(), $e);
        }
    }

    /**
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     * @throws CouldNotExtractMollieOrderIdException
     */
    public function getRemainingAmount(OrderEntity $order): float
    {
        return $this->getPayment($order)->getAmountRemaining();
    }

    public function getVoucherPaidAmount(OrderEntity $order): float
    {
        $payment = $this->getPayment($order);

        if ($payment->details === null) {
            return 0;
        }

        if (! property_exists($payment->details, 'vouchers')) {
            return 0;
        }

        $voucherAmount = 0;

        /** @var \stdClass $voucher */
        foreach ($payment->details->vouchers as $voucher) {
            $voucherAmount += (float) $voucher->amount->value;
        }

        return $voucherAmount;
    }

    /**
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     * @throws CouldNotExtractMollieOrderIdException
     */
    public function getRefundedAmount(OrderEntity $order): float
    {
        return $this->getPayment($order)->getAmountRefunded();
    }

    /**
     * @param array<mixed> $refunds
     */
    public function getPendingRefundAmount(array $refunds): float
    {
        $pendingRefundAmount = 0;

        /** @var array<mixed> $refund */
        foreach ($refunds as $refund) {
            if ($refund['status'] === 'pending') {
                $pendingRefundAmount += (float) $refund['amount']['value'];
            }
        }

        return $pendingRefundAmount;
    }

    private function getPayment(OrderEntity $order): Payment
    {
        $orderAttributes = new OrderAttributes($order);

        if ($orderAttributes->isTypeSubscription()) {
            // subscriptions do not have a mollie order
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
