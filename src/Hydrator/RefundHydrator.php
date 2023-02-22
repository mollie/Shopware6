<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;

use Kiener\MolliePayments\Components\RefundManager\DAL\Order\OrderExtension;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundEntity;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;

class RefundHydrator
{
    /**
     * @param Refund $refund
     * @param OrderEntity $order
     * @return array<string, mixed>
     */
    public function hydrate(Refund $refund, OrderEntity $order): array
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

        $internalDescription = null;

        /** @var RefundCollection $shopwareRefunds */
        $shopwareRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        // Lookup the correct refund for the internal description
        if ($shopwareRefunds !== null) {
            $shopwareRefunds = $shopwareRefunds->filterByProperty('mollieRefundId', $refund->id);

            /** @var RefundEntity $shopwareRefund */
            $shopwareRefund = $shopwareRefunds->first();
            if ($shopwareRefund !== null) {
                $internalDescription = $shopwareRefund->getInternalDescription();
            }
        }

        return [
            'id' => $refund->id,
            'orderId' => $refund->orderId,
            'paymentId' => $refund->paymentId,
            'amount' => $amount,
            'settlementAmount' => $settlementAmount,
            'description' => $refund->description,
            'internalDescription' => $internalDescription,
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
