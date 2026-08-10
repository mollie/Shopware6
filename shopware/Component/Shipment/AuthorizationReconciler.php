<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Shipment\Route\ShipOrderResponse;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Owns the Mollie Payments-API side of a shipment: capturing the gross amount of the shipped items
 * (incl. rounding difference), releasing the authorization that exceeds the shipped amount, and the
 * standalone reconciliation of orders that have nothing left to ship in Shopware but still carry an
 * open authorization.
 */
final class AuthorizationReconciler
{
    /**
     * Sub-cent tolerance for reconciliation amount comparisons (capture top-up / release decision).
     */
    private const RECONCILE_THRESHOLD = 0.005;

    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: ShipmentItemResolver::class)]
        private readonly ShipmentItemResolver $itemResolver,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Captures the shipped items via the Payments API and releases the authorization remainder when
     * the order is fully handled with cancellations. Returns the Mollie capture id, or null when the
     * capture failed (best-effort: the delivery state change must not be interrupted).
     *
     * @param array<string, mixed> $logContext
     */
    public function captureViaPaymentsApi(
        Payment $payment,
        ShippingItemCollection $shippingItems,
        OrderEntity $order,
        OrderLineItemCollection $lineItems,
        CurrencyEntity $currency,
        string $orderNumber,
        string $salesChannelId,
        bool $fullyShipped,
        array $logContext
    ): ?string {
        $paymentId = $payment->getId();

        // Each shipment captures the gross amount of exactly its own items (incl. their taxes).
        $createCapture = new CreateCapture($shippingItems, $currency->getIsoCode());

        $hasCancelledItems = $this->itemResolver->hasCancelledItems($lineItems);

        $captureAmount = $this->resolveCaptureAmount($payment, $shippingItems, $order, $lineItems, $currency, $orderNumber, $salesChannelId, $fullyShipped, $hasCancelledItems, $logContext);
        if ($captureAmount !== null) {
            $createCapture->setAmount($captureAmount);
        }

        $logContext['molliePaymentId'] = $paymentId;

        $this->logger->info('AuthorizationReconciler: calling Mollie createCapture (Payments API)', $logContext);

        try {
            $capture = $this->mollieGateway->createCapture($createCapture, $paymentId, $orderNumber, $salesChannelId);
        } catch (\Throwable $exception) {
            // Capturing at Mollie may fail (e.g. the payment was already captured because the merchant
            // set the order to paid manually). This must not interrupt the delivery state change, so we
            // only log the error and stop here.
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('AuthorizationReconciler: Mollie createCapture failed, skipping shipment', $logContext);

            return null;
        }

        // With cancellations the shipped items are captured above and the rest of the authorization
        // (cancelled items + rounding difference) is released so it is not charged to the customer.
        // Releasing is best-effort and asynchronous, so a failure must not undo the successful capture.
        if ($fullyShipped && $hasCancelledItems) {
            try {
                $this->logger->info('AuthorizationReconciler: order fully handled with cancellations, releasing remaining authorization (Payments API)', $logContext);
                $this->mollieGateway->releaseAuthorization($paymentId, $orderNumber, $salesChannelId);
            } catch (\Throwable $exception) {
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('AuthorizationReconciler: releasing authorization failed', $logContext);
            }
        }

        $logContext['mollieCaptureId'] = $capture->getId();

        $this->logger->info('AuthorizationReconciler: Mollie createCapture response', $logContext);

        return $capture->getId();
    }

    /**
     * Reconciles an order that has nothing left to ship in Shopware but still has an open Mollie
     * authorization (Payments API). This covers older orders captured with a too low (net) amount and
     * the rounding-difference line that never exists as a Shopware line item: the shipped items are
     * topped up to their gross amount, and any authorization beyond the shipped gross (cancelled
     * items, rounding difference) is released so it is not charged to the customer.
     *
     * @param array<string, mixed> $logContext
     */
    public function reconcileAuthorizedRemainder(
        OrderEntity $order,
        Payment $payment,
        CurrencyEntity $currency,
        string $taxStatus,
        string $orderNumber,
        string $salesChannelId,
        ?string $mollieOrderId,
        OrderDeliveryCollection $deliveryCollection,
        OrderLineItemCollection $lineItems,
        array $logContext
    ): ShipOrderResponse {
        $orderId = $order->getId();

        // The Orders API is line-item based; there is no single amount to top up here.
        if ($mollieOrderId !== null) {
            $this->logger->info('AuthorizationReconciler: nothing to ship, order is already fully shipped or cancelled', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $paymentId = $payment->getId();

        try {
            $freshPayment = $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);
        } catch (\Throwable $exception) {
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('AuthorizationReconciler: could not load Mollie payment for reconciliation', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $remaining = $freshPayment->getAmountRemaining();
        if ($remaining === null || $remaining->getValue() <= self::RECONCILE_THRESHOLD) {
            $this->logger->info('AuthorizationReconciler: nothing to ship and no open authorization to reconcile', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        $alreadyCaptured = $freshPayment->getCapturedAmount()?->getValue() ?? 0.0;
        $authorized = $freshPayment->getAmount()?->getValue() ?? 0.0;

        // Without cancellations the whole order was shipped, so the full authorized amount (incl. taxes
        // and rounding difference) is owed. With cancellations only the shipped items are owed; the
        // rest is released below.
        $target = $this->itemResolver->hasCancelledItems($lineItems)
            ? $this->itemResolver->sumShippedGross($lineItems, $deliveryCollection, $currency, $taxStatus)
            : $authorized;

        $shortfall = $target - $alreadyCaptured;
        $mollieId = '';

        // Top up the capture so the shipped items are fully captured incl. their taxes/rounding.
        if ($shortfall > self::RECONCILE_THRESHOLD) {
            $emptyItems = new ShippingItemCollection();
            $reconcileCapture = new CreateCapture($emptyItems, $currency->getIsoCode());
            $shortfallAmount = new Money($shortfall, $currency->getIsoCode());
            $reconcileCapture->setAmount($shortfallAmount);
            $reconcileCapture->setDescription(sprintf('Tax reconciliation for order %s', $orderNumber));

            try {
                $capture = $this->mollieGateway->createCapture($reconcileCapture, $paymentId, $orderNumber, $salesChannelId);
                $mollieId = $capture->getId();
                $logContext['reconciledAmount'] = $shortfall;
                $this->logger->info('AuthorizationReconciler: reconciled missing amount via capture', $logContext);
            } catch (\Throwable $exception) {
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('AuthorizationReconciler: reconciliation capture failed', $logContext);

                return new ShipOrderResponse('', $orderId, []);
            }
        }

        // Release the authorization that exceeds the target (cancelled items), so Mollie can settle the
        // payment to paid and the customer is not charged for it.
        if ($authorized - $target > self::RECONCILE_THRESHOLD) {
            try {
                $this->mollieGateway->releaseAuthorization($paymentId, $orderNumber, $salesChannelId);
                if ($mollieId === '') {
                    $mollieId = $paymentId;
                }
            } catch (\Throwable $exception) {
                $logContext['exception'] = $exception->getMessage();
                $this->logger->error('AuthorizationReconciler: releasing authorization during reconciliation failed', $logContext);
            }
        }

        if ($mollieId === '') {
            $this->logger->info('AuthorizationReconciler: nothing to reconcile', $logContext);

            return new ShipOrderResponse('', $orderId, []);
        }

        return new ShipOrderResponse($mollieId, $orderId, []);
    }

    /**
     * Resolves the amount to capture, or null to keep the default (the gross of exactly the shipped
     * line items). The rounding difference (Shopware allows 4 decimals per currency, Mollie only 2) is
     * never a Shopware line item, so it has to be added here in the cases below.
     *
     * @param array<string, mixed> $logContext
     */
    private function resolveCaptureAmount(
        Payment $payment,
        ShippingItemCollection $shippingItems,
        OrderEntity $order,
        OrderLineItemCollection $lineItems,
        CurrencyEntity $currency,
        string $orderNumber,
        string $salesChannelId,
        bool $fullyShipped,
        bool $hasCancelledItems,
        array $logContext
    ): ?Money {
        $paymentId = $payment->getId();

        // Full shipment without cancellations: the entire authorized amount is owed. Derive the capture
        // directly from the Mollie payment (authorized minus already captured) rather than summing the
        // shipped line items plus a separately tracked rounding difference. This lands the capture
        // exactly on the authorized total - incl. taxes and the sub-cent rounding difference - without
        // depending on recognizing the rounding line by SKU or metadata (both fail once a custom
        // rounding SKU is configured), and trues up the final shipment of a multi-shipment order
        // regardless of what earlier shipments captured.
        if ($fullyShipped && ! $hasCancelledItems) {
            $freshPayment = $this->resolveFreshPayment($paymentId, $orderNumber, $salesChannelId, $logContext);
            if ($freshPayment === null) {
                return null;
            }

            $authorized = $freshPayment->getAmount()?->getValue() ?? 0.0;
            $alreadyCaptured = $freshPayment->getCapturedAmount()?->getValue() ?? 0.0;
            $remainder = $authorized - $alreadyCaptured;
            if ($remainder <= self::RECONCILE_THRESHOLD) {
                return null;
            }

            return new Money($remainder, $currency->getIsoCode());
        }

        // With cancellations the rounding difference stays in the released remainder; later shipments
        // (prior shipments exist) are trued up by the full-shipment branch above. So only the very first
        // partial shipment of an uncancelled order needs to carry the rounding difference.
        if ($hasCancelledItems || $this->itemResolver->hasPriorShipments($lineItems)) {
            return null;
        }

        $roundingDiff = $this->resolveOrderRoundingDifference($order, $paymentId, $orderNumber, $salesChannelId, $logContext);
        if (abs($roundingDiff) <= self::RECONCILE_THRESHOLD) {
            return null;
        }

        return new Money($shippingItems->getTotalAmount() + $roundingDiff, $currency->getIsoCode());
    }

    /**
     * The rounding difference persisted on the order at payment creation, falling back to the value on
     * the Mollie payment for orders created before it was persisted.
     *
     * @param array<string, mixed> $logContext
     */
    private function resolveOrderRoundingDifference(OrderEntity $order, string $paymentId, string $orderNumber, string $salesChannelId, array $logContext): float
    {
        $mollieCustomFields = $order->getCustomFields()[Mollie::EXTENSION] ?? [];
        if (array_key_exists('rounding_diff', $mollieCustomFields)) {
            return (float) $mollieCustomFields['rounding_diff'];
        }

        return $this->resolveRoundingDifference($paymentId, $orderNumber, $salesChannelId, $logContext);
    }

    /**
     * The rounding difference tracked on the Mollie payment lines (Shopware allows 4 decimals per
     * currency, Mollie only 2). Fallback for orders created before it was persisted on the order.
     * Best-effort: returns 0.0 when the payment cannot be loaded.
     *
     * @param array<string, mixed> $logContext
     */
    private function resolveRoundingDifference(string $paymentId, string $orderNumber, string $salesChannelId, array $logContext): float
    {
        try {
            $payment = $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);

            return $payment->getRoundingDiff();
        } catch (\Throwable $exception) {
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('AuthorizationReconciler: could not resolve rounding difference', $logContext);

            return 0.0;
        }
    }

    /**
     * Loads the current Mollie payment so the full-shipment capture can be derived from its authorized
     * and already captured amounts. Best-effort: returns null when the payment cannot be loaded, so the
     * capture falls back to the shipped line-item total.
     *
     * @param array<string, mixed> $logContext
     */
    private function resolveFreshPayment(string $paymentId, string $orderNumber, string $salesChannelId, array $logContext): ?Payment
    {
        try {
            return $this->mollieGateway->getPayment($paymentId, $orderNumber, $salesChannelId);
        } catch (\Throwable $exception) {
            $logContext['exception'] = $exception->getMessage();
            $this->logger->error('AuthorizationReconciler: could not load Mollie payment for capture reconciliation', $logContext);

            return null;
        }
    }
}
