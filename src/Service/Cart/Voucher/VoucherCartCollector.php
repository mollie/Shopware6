<?php

namespace Kiener\MolliePayments\Service\Cart\Voucher;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VoucherCartCollector implements CartDataCollectorInterface
{

    public const VOUCHER_PERMITTED = 'mollie-voucher-permitted';

    /**
     * @var VoucherService
     */
    private $voucherService;


    /**
     * @param VoucherService $voucherService
     */
    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }


    /**
     * This function is used to verify if a voucher payment is permitted to be used.
     * It will just calculate this once, and then offer that information
     * in the DATA field of the cart object.
     *
     * @param CartDataCollection $data
     * @param Cart $original
     * @param SalesChannelContext $context
     * @param CartBehavior $behavior
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $cartHasVoucher = false;

        foreach ($original->getLineItems() as $item) {

            # get the final inherited voucher type of the product
            # this might even be from the parent
            $voucherType = $this->voucherService->getFinalVoucherType($item, $context);

            # if we have a valid voucher product
            # then we have to update the actual line item,
            # because the current one might be empty, if only our PARENT would be configured.
            if (VoucherType::isVoucherProduct($voucherType)) {

                $cartHasVoucher = true;

                # load current custom fields data of mollie
                # and overwrite the voucher type that we just searched
                $attributes = new LineItemAttributes($item);
                $attributes->setVoucherType($voucherType);

                $customFields = $item->getPayload()['customFields'];
                $customFields['mollie_payments'] = $attributes->toArray();

                $item->setPayloadValue('customFields', $customFields);
            }
        }

        $data->set(self::VOUCHER_PERMITTED, $cartHasVoucher);
    }

}
