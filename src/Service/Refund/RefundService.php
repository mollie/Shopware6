<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Exception\CouldNotCancelMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieRefundsException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Refund\Item\MollieRefundItem;
use Kiener\MolliePayments\Service\Stock\StockUpdater;
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
     * @var StockUpdater
     */
    private $stock;

    /**
     * @var RefundHydrator
     */
    private $refundHydrator;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;


    /**
     * @param Order $mollie
     * @param OrderService $orders
     * @param StockUpdater $stock
     * @param RefundHydrator $refundHydrator
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @throws \Exception
     */
    public function __construct(Order $mollie, OrderService $orders, StockUpdater $stock, RefundHydrator $refundHydrator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory)
    {
        $this->mollie = $mollie;
        $this->orders = $orders;
        $this->stock = $stock;
        $this->refundHydrator = $refundHydrator;
        $this->flowBuilderEventFactory = $flowBuilderEventFactory;

        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();
    }


    /**
     * @param OrderEntity $order
     * @param string $description
     * @param Context $context
     * @return Refund
     */
    public function refundFull(OrderEntity $order, string $description, Context $context): Refund
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);

        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, '');

        $refund = $mollieOrder->refundAll([
            'description' => $description,
        ]);

        $event = $this->flowBuilderEventFactory->buildRefundStartedEvent($order, $context);
        $this->flowBuilderDispatcher->dispatch($event);

        return $refund;
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param float $amount
     * @param array $lineItems
     * @param Context $context
     * @return Refund
     * @throws ApiException
     */
    public function refundAmount(OrderEntity $order, string $description, float $amount, array $lineItems, Context $context): Refund
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);

        $payment = $this->mollie->getCompletedPayment($mollieOrderId, $order->getSalesChannelId());

        $metadata = [];
        foreach ($lineItems as $item) {
            if ($item->getQuantity() > 0) {
                $metadata['composition'][] = [
                    'lineItemId' => $item->getMollieLineID(),
                    'quantity' => $item->getQuantity()
                ];
            }
        }

        $refund = $payment->refund([
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => $order->getCurrency()->getIsoCode()
            ],
            'description' => $description,
            'metadata' => json_encode($metadata),
        ]);

        if ($refund instanceof Refund) {
            $event = $this->flowBuilderEventFactory->buildRefundStartedEvent($order, $context);
            $this->flowBuilderDispatcher->dispatch($event);

            return $refund;
        }

        throw new \Exception('No refund could be created for order: ' . $order->getOrderNumber());
    }

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param array $refundItems
     * @param Context $context
     * @return Refund|void
     */
    public function refundItems(OrderEntity $order, string $description, array $refundItems, Context $context): Refund
    {
        $mollieOrderId = $this->orders->getMollieOrderId($order);
        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, '');


        $lines = [];

        /** @var MollieRefundItem $item */
        foreach ($refundItems as $item) {
            # quantities of 0 do not
            # work with the Mollie API
            if ($item->getQuantity() <= 0) {
                continue;
            }

            $lines[] = [
                'id' => $item->getMollieLineId(),
                'quantity' => $item->getQuantity(),
                'amount' => [
                    'value' => number_format($item->getAmount(), 2, '.', ''),
                    'currency' => $order->getCurrency()->getIsoCode()
                ],
            ];
        }

        # REFUND WITH MOLLIE
        # ---------------------------------------------------------------------------------------------
        $refund = $mollieOrder->refund([
            'description' => $description,
            'lines' => $lines,
        ]);

        if (!$refund instanceof Refund) {
            throw new \Exception('No refund could be created for order: ' . $order->getOrderNumber());
        }


        # RESET STOCK
        # ---------------------------------------------------------------------------------------------
        # if everything worked above, iterate through all our
        # refund items and increase their stock
        foreach ($refundItems as $item) {

            if (empty($item->getProductID())) {
                continue;
            }

            $this->stock->increaseStock($item->getProductID(), $item->getResetStock());
        }

        $event = $this->flowBuilderEventFactory->buildRefundStartedEvent($order, $context);
        $this->flowBuilderDispatcher->dispatch($event);

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
