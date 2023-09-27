<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;

use Kiener\MolliePayments\Components\RefundManager\DAL\Order\OrderExtension;
use Kiener\MolliePayments\Components\RefundManager\DAL\OrderLineItem\OrderLineItemExtension;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundEntity;
use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemEntity;
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

        $metaData = new \stdClass();

        if (property_exists($refund, 'metadata')) {
            /** @var \stdClass|string $metaData */
            $metaData = $refund->metadata;
            if (is_string($metaData)) {
                $metaData = json_decode($metaData);
            }
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

                $refundLineItems = $shopwareRefund->getRefundItems()->getElements();

                $metaData->composition = [];
                /** @var RefundItemEntity $refundLineItem */
                foreach ($refundLineItems as $refundLineItem) {
                    $metaData->type = $refundLineItem->getType();
                    $metaData->composition[]=[
                        'swLineId' => (string)$refundLineItem->getOrderLineItemId(),
                        'swLineVersionId' => (string)$refundLineItem->getOrderLineItemVersionId(),
                        'mollieLineId' => $refundLineItem->getMollieLineId(),
                        'swReference' => $refundLineItem->getReference(),
                        'quantity' => $refundLineItem->getQuantity(),
                        'amount' => $refundLineItem->getAmount()
                    ];
                }
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
            'metadata' => $metaData,
        ];
    }
}
