<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

final class OrderStateSettings
{
    public const KEY_STATE_PAID = 'orderStateWithAPaidTransaction';
    public const KEY_STATE_FAILED = 'orderStateWithAFailedTransaction';
    public const KEY_STATE_CANCELLED = 'orderStateWithACancelledTransaction';
    public const KEY_STATE_AUTHORIZED = 'orderStateWithAAuthorizedTransaction';
    public const KEY_STATE_CHARGE_BACK = 'orderStateWithAChargebackTransaction';
    public const KEY_STATE_REFUND = 'orderStateWithRefundTransaction';
    public const KEY_STATE_PARTIAL_REFUND = 'orderStateWithPartialRefundTransaction';
    public const KEY_STATE_FINAL = 'orderStateFinalState';
    public const SKIP_STATE = 'skip';

    /**
     * @param array<string,string> $stateMapping
     */
    public function __construct(
        private array $stateMapping = [],
        private ?string $finalOrderState = null,
    ) {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $paidOrderState = $settings[self::KEY_STATE_PAID] ?? self::SKIP_STATE;
        $failedOrderState = $settings[self::KEY_STATE_FAILED] ?? self::SKIP_STATE;
        $cancelledOrderState = $settings[self::KEY_STATE_CANCELLED] ?? self::SKIP_STATE;
        $authorizedOrderState = $settings[self::KEY_STATE_AUTHORIZED] ?? self::SKIP_STATE;
        $chargeBackOrderState = $settings[self::KEY_STATE_CHARGE_BACK] ?? self::SKIP_STATE;
        $refundOrderState = $settings[self::KEY_STATE_REFUND] ?? self::SKIP_STATE;
        $partialRefundOrderState = $settings[self::KEY_STATE_PARTIAL_REFUND] ?? self::SKIP_STATE;
        $finalOrderState = $settings[self::KEY_STATE_FINAL] ?? null;

        $stateMapping = [
            OrderTransactionStates::STATE_PAID => $paidOrderState,
            OrderTransactionStates::STATE_FAILED => $failedOrderState,
            OrderTransactionStates::STATE_CANCELLED => $cancelledOrderState,
            OrderTransactionStates::STATE_AUTHORIZED => $authorizedOrderState,
            OrderTransactionStates::STATE_CHARGEBACK => $chargeBackOrderState,
            OrderTransactionStates::STATE_REFUNDED => $refundOrderState,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED => $partialRefundOrderState,
        ];

        return new self($stateMapping, $finalOrderState);
    }

    public function getFinalOrderState(): ?string
    {
        return $this->finalOrderState;
    }

    public function getStatus(string $shopwarePaymentStatus): ?string
    {
        $status = $this->stateMapping[$shopwarePaymentStatus] ?? self::SKIP_STATE;
        if ($status === self::SKIP_STATE) {
            return null;
        }

        return $status;
    }
}
