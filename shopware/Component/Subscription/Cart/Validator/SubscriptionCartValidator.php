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
use Mollie\Shopware\Component\Subscription\Cart\Error\MixedCartBlockError;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
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
        private readonly LineItemAnalyzer $lineItemAnalyzer
    ) {
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $this->clearError($cart);

        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($context->getSalesChannelId());

        if (! $subscriptionSettings->isEnabled()) {
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

        $lineItems = new LineItemCollection($cart->getLineItems()->getFlat());

        if (! $this->lineItemAnalyzer->hasSubscriptionProduct($lineItems)) {
            return;
        }

        if ($this->lineItemAnalyzer->hasMixedLineItems($lineItems)) {
            $errors->add(new MixedCartBlockError());
        }

        if (! $paymentMethodHandler instanceof SubscriptionAwareInterface) {
            $errors->add(new InvalidPaymentMethodError());
        }
    }

    private function clearError(Cart $cart): void
    {
        $list = new ErrorCollection();

        foreach ($cart->getErrors() as $error) {
            if (! $error instanceof InvalidGuestAccountError
                && ! $error instanceof MixedCartBlockError
                && ! $error instanceof InvalidPaymentMethodError) {
                $list->add($error);
            }
        }

        $cart->setErrors($list);
    }
}
