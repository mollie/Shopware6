<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VoucherPayment extends PaymentHandler
{

    public const TYPE_ECO = "1";
    public const TYPE_MEAL = "2";
    public const TYPE_GIFT = "3";

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
     * @param array $orderData
     * @param OrderEntity $orderEntity
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @return array
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
     * Searches the order line item with the provided ID and tries
     * to find out if any voucherType has been set for that product.
     * Depending on the configuration, a matching category for Mollie will be returned.
     */
    private function getProductCategory(OrderEntity $orderEntity, string $lineItemId): string
    {
        /** @var OrderLineItemEntity $lineItem */
        foreach ($orderEntity->getLineItems() as $lineItem) {

            if (!$lineItem->getId() === $lineItemId) {
                continue;
            }

            # try to get the voucher type from the line item
            $attributes = new OrderLineItemEntityAttributes($lineItem);
            $voucherType = $attributes->getVoucherType();

            # older versions do not have custom fields in the line item.
            # in that case, try to ask our product, if existing if it has data
            if (empty($voucherType) && $lineItem->getProduct() instanceof ProductEntity) {
                $attributes = new ProductAttributes($lineItem->getProduct());
                $voucherType = $attributes->getVoucherType();
            }

            if ($voucherType === self::TYPE_ECO) {
                return 'eco';
            }

            if ($voucherType === self::TYPE_MEAL) {
                return 'meal';
            }

            if ($voucherType === self::TYPE_GIFT) {
                return 'gift';
            }
        }

        return '';
    }
}
