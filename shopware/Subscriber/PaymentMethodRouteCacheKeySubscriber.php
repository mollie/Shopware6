<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Mollie payment methods are shown/hidden dynamically by the payment-method removers - the
 * availability remover hides methods depending on delivery/billing country, cart amount,
 * currency and whether the cart contains a subscription product. Shopware caches the
 * payment-method route, so unless those same factors are folded into the cache key the
 * cached response keeps returning methods that should already have been removed.
 *
 * This subscriber adds the relevant parts so the cache varies along the exact dimensions
 * the removers depend on.
 */
final class PaymentMethodRouteCacheKeySubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        private readonly CartService $cartService
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentMethodRouteCacheKeyEvent::class => 'onGenerateCacheKey',
        ];
    }

    public function onGenerateCacheKey(PaymentMethodRouteCacheKeyEvent $event): void
    {
        $context = $event->getContext();
        $originalRuleIds = $context->getRuleIds();

        /**
         * the cart service changes the rule ids based on cart.
         * after failed payment we are not in cart anymore but instead on edit order
         * in this case the cart is empty so the rules will be reset
         */
        $cart = $this->cartService->getCart($context->getToken(), $context);

        /* we have to collect the original rules before cart service is called and set them again */
        $context->setRuleIds($originalRuleIds);

        $cacheParts = [];
        $cacheParts = $this->addMollieLimitsKey($context, $cacheParts);
        $cacheParts = $this->addSubscriptionKey($cart, $cacheParts);
        $cacheParts = $this->addCartAmountKey($cart, $cacheParts);
        $cacheParts = $this->addCurrencyCodeKey($context, $cacheParts);
        $cacheParts = $this->addBillingAddressKey($context, $cacheParts);

        $parts = $event->getParts();
        $parts[] = md5(implode('-', $cacheParts));
        $event->setParts($parts);
    }

    /**
     * @param array<mixed> $parts
     *
     * @return array<mixed>
     */
    private function addMollieLimitsKey(SalesChannelContext $context, array $parts): array
    {
        $paymentSettings = $this->settingsService->getPaymentSettings($context->getSalesChannelId());

        if ($paymentSettings->useMollieLimits()) {
            $parts[] = 'with-limits';
        } else {
            $parts[] = 'without-limits';
        }

        return $parts;
    }

    /**
     * @param array<mixed> $parts
     *
     * @return array<mixed>
     */
    private function addSubscriptionKey(Cart $cart, array $parts): array
    {
        if ($this->lineItemAnalyzer->hasSubscriptionProduct($cart->getLineItems())) {
            $parts[] = 'with-subscription';
        } else {
            $parts[] = 'without-subscription';
        }

        return $parts;
    }

    /**
     * @param array<mixed> $cacheParts
     *
     * @return array<mixed>
     */
    private function addCartAmountKey(Cart $cart, array $cacheParts): array
    {
        $cacheParts[] = $cart->getPrice()->getTotalPrice();

        return $cacheParts;
    }

    /**
     * @param array<mixed> $cacheParts
     *
     * @return array<mixed>
     */
    private function addCurrencyCodeKey(SalesChannelContext $context, array $cacheParts): array
    {
        $cacheParts[] = $context->getCurrency()->getIsoCode();

        return $cacheParts;
    }

    /**
     * @param array<mixed> $cacheParts
     *
     * @return array<mixed>
     */
    private function addBillingAddressKey(SalesChannelContext $context, array $cacheParts): array
    {
        $customer = $context->getCustomer();

        if ($customer === null) {
            return $cacheParts;
        }

        $billingAddress = $customer->getActiveBillingAddress();

        if ($billingAddress === null) {
            return $cacheParts;
        }

        $cacheParts[] = $billingAddress->getId();

        return $cacheParts;
    }
}
