<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Cart\Validator;

use Kiener\MolliePayments\Components\Subscription\Cart\Error\PaymentMethodAvailabilityNotice;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AvailabilityInformationValidator implements CartValidatorInterface
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
        $settings = $this->pluginSettings->getSettings($salesChannelContext->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            $this->clearError($cart);
            return;
        }

        $foundSubscriptionItem = null;

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $attributes = new LineItemAttributes($lineItem);

            if (!$attributes->isSubscriptionProduct()) {
                continue;
            }

            $foundSubscriptionItem = $lineItem;
        }

        if ($foundSubscriptionItem instanceof LineItem) {
            $errorCollection->add(new PaymentMethodAvailabilityNotice($foundSubscriptionItem->getId()));
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

        foreach ($cart->getErrors() as $error) {
            if (!$error instanceof PaymentMethodAvailabilityNotice) {
                $list->add($error);
            }
        }

        $cart->setErrors($list);
    }
}
