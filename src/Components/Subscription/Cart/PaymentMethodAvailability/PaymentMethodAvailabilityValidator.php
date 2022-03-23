<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Cart\PaymentMethodAvailability;

use Kiener\MolliePayments\Components\Subscription\Cart\PaymentMethodAvailability\Error\PaymentMethodAvailabilityNotice;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;



class PaymentMethodAvailabilityValidator implements CartValidatorInterface
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


        $foundSubscriptionItem = null;

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {

            $customFields = $lineItem->getPayload()['customFields'];

            if (!isset($customFields["mollie_subscription"])) {
                continue;
            }

            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            if (count($customFields["mollie_subscription"]) <= 0) {
                continue;
            }

            if ($customFields["mollie_subscription"]["mollie_subscription_product"]) {
                $foundSubscriptionItem = $lineItem;
                break;
            }
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

        /** @var Error $error */
        foreach ($cart->getErrors() as $error) {

            if (!$error instanceof PaymentMethodAvailabilityNotice) {
                $list->add($error);
            }
        }

        $cart->setErrors($list);
    }

}
