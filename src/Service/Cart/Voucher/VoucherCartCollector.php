<?php

namespace Kiener\MolliePayments\Service\Cart\Voucher;

use Kiener\MolliePayments\Controller\Api\PaymentMethodController;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class VoucherCartCollector implements CartDataCollectorInterface
{
    public const VOUCHER_PERMITTED = 'mollie-voucher-permitted';

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $repoPaymentMethods;

    /**
     * @var VoucherService
     */
    private $voucherService;


    /**
     * @param VoucherService $voucherService
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository
     */
    public function __construct(VoucherService $voucherService, PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->voucherService = $voucherService;
        $this->repoPaymentMethods = $paymentMethodRepository;
    }


    /**
     * This function is used to verify if a voucher payment is permitted to be used.
     * It will just calculate this once, and then offer that information
     * in the DATA field of the cart object.
     *
     * @param CartDataCollection<mixed> $data
     * @param Cart $original
     * @param SalesChannelContext $context
     * @param CartBehavior $behavior
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $salesChannelHasVoucherMethod = true;

        # if we have a lot of products, the performance might not be good enough at the moment.
        # we try to improve this as first step, by only verifying our products
        # if we even have the voucher payment method assigned to our Sales Channel.
        # if it's not assigned anyway, then we can simply skip that step
        /** @var null|string[] $paymentMethodIDs */
        $paymentMethodIDs = $context->getSalesChannel()->getPaymentMethodIds();

        if (is_array($paymentMethodIDs)) {
            $voucherID = $this->getVoucherID($context->getContext());
            $salesChannelHasVoucherMethod = in_array($voucherID, $paymentMethodIDs, true);
        }


        $cartHasVoucher = false;

        if ($salesChannelHasVoucherMethod) {
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
        }

        $data->set(self::VOUCHER_PERMITTED, $cartHasVoucher);
    }


    /**
     * @param Context $context
     * @return string
     */
    private function getVoucherID(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', VoucherPayment::class));

        // Get payment methods
        /** @var array<string> $paymentMethods */
        $paymentMethods = $this->repoPaymentMethods->searchIds($criteria, $context)->getIds();

        return (string)$paymentMethods[0];
    }
}
