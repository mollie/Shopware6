<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VoucherPayment extends PaymentHandler
{
    /**
     *
     */
    public const PAYMENT_METHOD_NAME = 'voucher';

    /**
     *
     */
    public const PAYMENT_METHOD_DESCRIPTION = 'Voucher';

    /**
     * @var string
     */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;


    /**
     * @param array<mixed> $orderData
     * @param OrderEntity $orderEntity
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $lineItems = $orderData['lines'];

        # add the category as mentioned here
        # https://docs.mollie.com/reference/v2/orders-api/create-order
        foreach ($lineItems as &$line) {
            $orderLineItemID = $line['metadata']['orderLineItemId'];

            $category = $this->getProductCategory($orderEntity, $orderLineItemID);

            if (!empty($category)) {
                $line['category'] = $category;
            }
        }

        $orderData['lines'] = $lineItems;

        return $orderData;
    }

    /**
     *
     * Searches the order line item with the provided ID and tries
     * to find out if any voucherType has been set for that product.
     * Depending on the configuration, a matching category for Mollie will be returned.
     * @param OrderEntity $orderEntity
     * @param string $lineItemId
     * @return string
     */
    private function getProductCategory(OrderEntity $orderEntity, string $lineItemId): string
    {
        if (!$orderEntity->getLineItems() instanceof OrderLineItemCollection) {
            return '';
        }

        foreach ($orderEntity->getLineItems() as $lineItem) {
            if ($lineItem->getId() !== $lineItemId) {
                continue;
            }

            # try to get the voucher type from the line item
            $attributes = new OrderLineItemEntityAttributes($lineItem);
            $voucherType = $attributes->getVoucherType();


            if ($voucherType === VoucherType::TYPE_ECO) {
                return 'eco';
            }

            if ($voucherType === VoucherType::TYPE_MEAL) {
                return 'meal';
            }

            if ($voucherType === VoucherType::TYPE_GIFT) {
                return 'gift';
            }
        }

        return '';
    }
}
