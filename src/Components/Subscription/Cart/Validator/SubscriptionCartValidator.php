<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Cart\Validator;

use Kiener\MolliePayments\Components\Subscription\Cart\Error\InvalidGuestAccountError;
use Kiener\MolliePayments\Components\Subscription\Cart\Error\InvalidPaymentMethodError;
use Kiener\MolliePayments\Components\Subscription\Cart\Error\MixedCartBlockError;
use Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover\SubscriptionRemover;
use Kiener\MolliePayments\Components\Subscription\Services\Validator\MixedCartValidator;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionCartValidator implements CartValidatorInterface
{
    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var MixedCartValidator
     */
    private $mixedCartValidator;

    /**
     * @param SettingsService $pluginSettings
     */
    public function __construct(SettingsService $pluginSettings)
    {
        $this->pluginSettings = $pluginSettings;

        $this->mixedCartValidator = new MixedCartValidator();
    }


    /**
     * @param Cart $cart
     * @param ErrorCollection $errorCollection
     * @param SalesChannelContext $salesChannelContext
     * @return void
     */
    public function validate(Cart $cart, ErrorCollection $errorCollection, SalesChannelContext $salesChannelContext): void
    {
        # always clear previous errors first
        $this->clearError($cart);


        $settings = $this->pluginSettings->getSettings($salesChannelContext->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            return;
        }

        # --------------------------------------------------------------------------------------------
        # first verify if we have a customer
        # if we do not have one yet, then we do NOT block the cart
        # this would just lead to weird errors, while the customer is not yet logged in
        if ($salesChannelContext->getCustomer() === null) {
            return;
        }

        # --------------------------------------------------------------------------------------------
        # now verify if we even have a subscription cart
        # if we don't have one, then just do nothing
        if (!$this->isSubscriptionCart($cart)) {
            return;
        }

        # --------------------------------------------------------------------------------------------
        # now check if we have a mixed cart.
        # this is not allowed!
        $isMixedCart = $this->mixedCartValidator->isMixedCart($cart);

        if ($isMixedCart) {
            $errorCollection->add(new MixedCartBlockError());
        }

        # --------------------------------------------------------------------------------------------
        # check if our customer is NO guest
        # only real users and accounts are allowed for subscriptions
        if ($salesChannelContext->getCustomer()->getGuest()) {
            $errorCollection->add(new InvalidGuestAccountError());
        }

        # --------------------------------------------------------------------------------------------
        # verify that our selected payment method is
        # indeed correct and the one from our list of available method.
        $paymentMethod = $salesChannelContext->getPaymentMethod();
        $paymentAttributes = new PaymentMethodAttributes($paymentMethod);

        $isAllowed = in_array($paymentAttributes->getMollieIdentifier(), SubscriptionRemover::ALLOWED_METHODS);

        if (!$isAllowed) {
            $errorCollection->add(new InvalidPaymentMethodError());
        }
    }

    /**
     * @param Cart $cart
     */
    private function clearError(Cart $cart): void
    {
        $list = new ErrorCollection();

        foreach ($cart->getErrors() as $error) {
            if (!$error instanceof InvalidGuestAccountError &&
                !$error instanceof MixedCartBlockError &&
                !$error instanceof InvalidPaymentMethodError) {
                $list->add($error);
            }
        }

        $cart->setErrors($list);
    }


    /**
     * @param Cart $cart
     * @return bool
     */
    private function isSubscriptionCart(Cart $cart): bool
    {
        foreach ($cart->getLineItems() as $lineItem) {
            $attribute = new LineItemAttributes($lineItem);

            if ($attribute->isSubscriptionProduct()) {
                return true;
            }
        }

        return false;
    }
}
