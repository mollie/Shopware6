<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Cart\Error\MixedCartBlockError;


use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class MixedCartBlockValidator implements CartValidatorInterface
{

    /**
     * @var SettingsService
     */
    private $pluginSettings;


    /**
     * @param SettingsService $pluginSettings
     */
    public function __construct(SettingsService $pluginSettings)
    {
        $this->pluginSettings = $pluginSettings;
    }


    /**
     * @param Cart $cart
     * @param ErrorCollection $errorCollection
     * @param SalesChannelContext $salesChannelContext
     * @return void
     */
    public function validate(Cart $cart, ErrorCollection $errorCollection, SalesChannelContext $salesChannelContext): void
    {
        $settings = $this->pluginSettings->getSettings();

        if (!$settings->isSubscriptionsEnableBeta()) {
            return;
        }


        $isMixedCart = false;
        $subscriptionItemsCount = 0;
        $otherItemsCount = 0;

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {

            $attributes = new LineItemAttributes($lineItem);

            if ($attributes->isSubscriptionProduct()) {
                $subscriptionItemsCount++;
            } else {
                $otherItemsCount++;
            }

            if ($otherItemsCount > 0) {
                # mixed cart with other items
                $isMixedCart = true;
            }

            if ($subscriptionItemsCount > 1) {
                # mixed cart with multiple subscription items
                $isMixedCart = true;
            }
        }

        if ($subscriptionItemsCount > 1 && $isMixedCart) {
            $errorCollection->add(new MixedCartBlockError());
        } else {
            $this->clearError($cart);
        }
    }

    /**
     * @param Cart $cart
     */
    private function clearError(Cart $cart): void
    {
        $list = new ErrorCollection();

        /** @var Error $error */
        foreach ($cart->getErrors() as $error) {

            if (!$error instanceof MixedCartBlockError) {
                $list->add($error);
            }
        }

        $cart->setErrors($list);
    }

}
