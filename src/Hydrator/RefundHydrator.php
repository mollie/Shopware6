<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;

use Mollie\Api\Resources\Refund;

class RefundHydrator
{
    /**
     * @param Refund $refund
     * @return array<string, mixed>
     */
    public function hydrate(Refund $refund): array
    {
        $amount = null;
        if ($refund->amount instanceof \stdClass) {
            $amount = [
                'value' => $refund->amount->value,
                'currency' => $refund->amount->currency,
            ];
        }

        $settlementAmount = null;
        if ($refund->settlementAmount instanceof \stdClass) {
            $settlementAmount = [
                'value' => $refund->settlementAmount->value,
                'currency' => $refund->settlementAmount->currency,
            ];
        }

        $metaData = '';

        if (property_exists($refund, 'metadata')) {
            $metaData = (string)$refund->metadata;
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
            'metadata' => json_decode($metaData, true),
        ];
    }
}
