<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cart\Validator;

use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidGuestAccountError;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidPaymentMethodError;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SubscriptionCartValidator implements CartValidatorInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer
    ) {
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $this->clearError($cart);

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($context->getSalesChannelId());

        if (! $subscriptionSettings->isEnabled()) {
            return;
        }

        $lineItems = new LineItemCollection($cart->getLineItems()->getFlat());
        if (! $this->lineItemAnalyzer->hasSubscriptionProduct($lineItems)) {
            return;
        }

        $shopwarePaymentMethod = $context->getPaymentMethod();

        $paymentMethodHandler = $this->paymentHandlerLocator->findByIdentifier($shopwarePaymentMethod->getHandlerIdentifier());

        if (! $paymentMethodHandler instanceof AbstractMolliePaymentHandler) {
            return;
        }

        $customer = $context->getCustomer();
        if ($customer === null) {
            return;
        }

        if ($customer->getGuest()) {
            $errors->add(new InvalidGuestAccountError());
        }

        if (! $paymentMethodHandler instanceof SubscriptionAwareInterface) {
            $errors->add(new InvalidPaymentMethodError());
        }
    }

    private function clearError(Cart $cart): void
    {
        $cart->getErrors()->remove(InvalidGuestAccountError::KEY);
        $cart->getErrors()->remove(InvalidPaymentMethodError::KEY);
    }
}
