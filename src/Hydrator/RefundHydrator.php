<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;

use Mollie\Api\Resources\Refund;

class RefundHydrator
{
    /**
     * @param Refund $refund
     * @return array
     */
    public static function hydrate(Refund $refund): array
    {
        $amount = null;
        if (!is_null($refund->amount)) {
            $amount = [
                'value' => $refund->amount->value,
                'currency' => $refund->amount->currency,
            ];
        }

        $settlementAmount = null;
        if (!is_null($refund->settlementAmount)) {
            $settlementAmount = [
                'value' => $refund->settlementAmount->value,
                'currency' => $refund->settlementAmount->currency,
            ];
        }

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
    }
}
