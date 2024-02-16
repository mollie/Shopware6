<?php

namespace Kiener\MolliePayments\Compatibility\Storefront\Route\PaymentMethodRoute\Cache;

use Kiener\MolliePayments\Service\Cart\Voucher\VoucherCartCollector;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedPaymentMethodRoute64 implements EventSubscriberInterface
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var SettingsService
     */
    private $pluginSettings;


    /**
     * @param SettingsService $pluginSettings
     * @param CartService $cartService
     */
    public function __construct(SettingsService $pluginSettings, CartService $cartService)
    {
        $this->pluginSettings = $pluginSettings;
        $this->cartService = $cartService;
    }


    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteCacheKeyEvent::class => 'onGenerateCacheKey',
        ];
    }

    /**
     * This function will make sure that we have a working cache key for all dynamic payment method
     * situations that could occur.
     * So we need to determine if we have a voucher product in it, or not...otherwise the dynamic display
     * of these payment methods (in other route handlers) would not work.
     * @param PaymentMethodRouteCacheKeyEvent $event
     */
    public function onGenerateCacheKey(PaymentMethodRouteCacheKeyEvent $event): void
    {
        $originalRuleIds = $event->getContext()->getRuleIds();

        /**
         * the cart service changes the rule ids based on cart.
         * after failed payment we are not in cart anymore but instead on edit order
         * in this case the cart is empty so the rules will be reset
         */
        $cart = $this->cartService->getCart($event->getContext()->getToken(), $event->getContext());

        /** we have to collect the original rules before cart service is called and set them again */
        $event->getContext()->setRuleIds($originalRuleIds);

        $parts = $event->getParts();
        $cacheParts = [];
        $cacheParts = $this->addVoucherKey($cart, $cacheParts);
        $cacheParts = $this->addMollieLimitsKey($cacheParts);
        $cacheParts = $this->addSubscriptionKey($cart, $cacheParts);
        $cacheParts = $this->addCartAmountKey($cart, $cacheParts);
        $cacheParts = $this->addCurrencyCodeKey($event->getContext(), $cacheParts);
        $cacheParts = $this->addBillingAddressKey($event->getContext(), $cacheParts);

        $parts[] = md5(implode('-', $cacheParts));
        $event->setParts($parts);
    }

    /**
     * @param Cart $cart
     * @param array<mixed> $parts
     *
     * @return array<mixed>
     */
    private function addVoucherKey(Cart $cart, array $parts): array
    {
        $voucherPermitted = (bool)$cart->getData()->get(VoucherCartCollector::VOUCHER_PERMITTED);

        if ($voucherPermitted) {
            $parts[] = 'with-voucher';
        } else {
            $parts[] = 'without-voucher';
        }

        return $parts;
    }

    /**
     * @param array<mixed> $parts
     * @return array<mixed>
     */
    private function addMollieLimitsKey(array $parts): array
    {
        $settings = $this->pluginSettings->getSettings();

        if ($settings->getUseMolliePaymentMethodLimits()) {
            $parts[] = 'with-limits';
        } else {
            $parts[] = 'without-limits';
        }

        return $parts;
    }

    /**
     * @param Cart $cart
     * @param array<mixed> $parts
     *
     * @return array<mixed>
     */
    private function addSubscriptionKey(Cart $cart, array $parts): array
    {
        $hasSubscriptionItems = $this->isSubscriptionCart($cart);

        if ($hasSubscriptionItems) {
            $parts[] = 'with-subscription';
        } else {
            $parts[] = 'without-subscription';
        }

        return $parts;
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

    private function addCartAmountKey(Cart $cart, array $cacheParts): array
    {
        $cacheParts[] = $cart->getPrice()->getTotalPrice();
        return $cacheParts;
    }

    private function addCurrencyCodeKey(SalesChannelContext $context, array $cacheParts)
    {
        $cacheParts[] = $context->getCurrency()->getIsoCode();
        return $cacheParts;
    }

    private function addBillingAddressKey(SalesChannelContext $context, array $cacheParts)
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            return $cacheParts;
        }

        $billingAddress = $customer->getActiveBillingAddress();

        if ($billingAddress === null) {
            return $cacheParts;
        }

        $cacheParts[]=$billingAddress->getId();

        return $cacheParts;
    }
}
