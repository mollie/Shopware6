<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\RefundCollection as MollieRefundCollection;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * Maps the refunds Mollie knows about onto the line items and deliveries of the order. Every
 * refund route needs this to tell the admin what is already refunded per position, so it lives
 * next to them instead of inside one of them.
 *
 * Not final: the routes are unit tested against a fake of this class.
 */
class RefundCompositionBuilder
{
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * Builds a map of refunded quantities per order line item / delivery, keyed by its id.
     * The quantity is derived from the refunded amount (rounded up per unit), so a partial
     * amount of a single unit counts as one refunded unit and only rises once a further
     * unit's worth is refunded. Canceled and failed refunds are ignored, pending refunds
     * are included since those amounts can no longer be refunded again.
     *
     * @return array<string, int>
     */
    public function buildRefundedQuantities(OrderEntity $order, MollieRefundCollection $refunds): array
    {
        $refundedAmounts = $this->buildRefundedAmounts($order, $refunds);

        if (count($refundedAmounts) === 0) {
            return [];
        }

        $lineInfo = $this->buildLineInfo($order);

        $quantities = [];

        foreach ($refundedAmounts as $shopwareId => $amount) {
            if (! isset($lineInfo[$shopwareId])) {
                continue;
            }

            $quantities[$shopwareId] = $this->deriveRefundedUnits($amount, $lineInfo[$shopwareId]['max'], $lineInfo[$shopwareId]['quantity']);
        }

        return $quantities;
    }

    /**
     * Builds a map of the refunded amount per order line item / delivery, keyed by its id.
     * A refund item stores either full units (quantity > 0, amount is per unit) or a partial
     * remainder (quantity 0, amount is the total), so both cases are summed accordingly.
     * Canceled and failed refunds are ignored, pending refunds are included since those
     * amounts can no longer be refunded again.
     *
     * @return array<string, float>
     */
    public function buildRefundedAmounts(OrderEntity $order, MollieRefundCollection $refunds): array
    {
        $amounts = [];

        $dalRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        if (! $dalRefunds instanceof RefundCollection) {
            return $amounts;
        }

        /** @var RefundEntity $dalRefund */
        foreach ($dalRefunds as $dalRefund) {
            $mollieRefund = $refunds->findByMollieId((string) $dalRefund->getMollieRefundId());

            if ($mollieRefund === null) {
                continue;
            }

            $status = $mollieRefund->getStatus();
            if ($status === RefundStatus::Canceled || $status === RefundStatus::Failed) {
                continue;
            }

            foreach ($dalRefund->getRefundItems() as $refundItem) {
                $shopwareId = $refundItem->getOrderLineItemId() ?? $refundItem->getOrderDeliveryId();
                if ($shopwareId === null || $shopwareId === '') {
                    continue;
                }

                $quantity = $refundItem->getQuantity();
                $amount = $quantity > 0 ? $refundItem->getAmount() * $quantity : $refundItem->getAmount();

                $amounts[$shopwareId] = ($amounts[$shopwareId] ?? 0.0) + $amount;
            }
        }

        return $amounts;
    }

    /**
     * Builds a map of the maximum refundable amount and quantity per order line item /
     * delivery, keyed by its id. The max is the line total (unit price * quantity); for net
     * orders the line tax is added on top, since the refund can include it.
     *
     * @return array<string, array{max: float, quantity: int}>
     */
    public function buildLineInfo(OrderEntity $order): array
    {
        $isGross = $order->getTaxStatus() === CartPrice::TAX_STATE_GROSS;

        $info = [];

        foreach (CartStruct::fromOrder($order)->jsonSerialize() as $cartItem) {
            $shopware = $cartItem->getShopware();

            if ($shopware->isPromotion()) {
                continue;
            }

            $lineMax = $shopware->getTotalPrice();
            if (! $isGross) {
                $lineMax += $shopware->getTax()->getTotalItemTax();
            }

            $info[$shopware->getId()] = [
                'max' => $lineMax,
                'quantity' => $shopware->getQuantity(),
            ];
        }

        return $info;
    }

    public function enrichRefundsWithComposition(MollieRefundCollection $mollieRefunds, OrderEntity $order): MollieRefundCollection
    {
        $dalRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        if (! $dalRefunds instanceof RefundCollection) {
            return $mollieRefunds;
        }

        /** @var RefundEntity $dalRefund */
        foreach ($dalRefunds as $dalRefund) {
            $mollieRefundId = (string) $dalRefund->getMollieRefundId();
            $mollieRefund = $mollieRefunds->findByMollieId($mollieRefundId);

            if ($mollieRefund === null) {
                continue;
            }

            $mollieRefund->setRefundItems($dalRefund->getRefundItems());
            $mollieRefund->setInternalDescription((string) $dalRefund->getInternalDescription());
        }

        return $mollieRefunds;
    }

    /**
     * Derives how many units of a line item are covered by the refunded amount, rounded up.
     * A partial amount of a single unit counts as one refunded unit; the result never
     * exceeds the line item quantity.
     */
    private function deriveRefundedUnits(float $refundedAmount, float $lineMax, int $quantity): int
    {
        if ($refundedAmount <= 0.0 || $lineMax <= 0.0 || $quantity <= 0) {
            return 0;
        }

        $units = (int) ceil(($refundedAmount - self::AMOUNT_TOLERANCE) * $quantity / $lineMax);

        return max(0, min($quantity, $units));
    }
}
