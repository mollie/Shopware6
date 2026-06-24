<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cart\Validator;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Cart\Error\PaymentMethodAvailabilityNotice;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AvailabilityInformationValidator implements CartValidatorInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer
    ) {
    }

    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($context->getSalesChannelId());

        if (! $subscriptionSettings->isEnabled()) {
            $this->clearError($cart);

            return;
        }
        $lineItems = new LineItemCollection($cart->getLineItems()->getFlat());

        /** @var ?LineItem $firstSubscriptionItem */
        $firstSubscriptionItem = $this->lineItemAnalyzer->getFirstSubscriptionProduct($lineItems);
        if ($firstSubscriptionItem === null) {
            $this->clearError($cart);

            return;
        }

        $errors->add(new PaymentMethodAvailabilityNotice($firstSubscriptionItem->getId()));
    }

    private function clearError(Cart $cart): void
    {
        $cart->getErrors()->remove(PaymentMethodAvailabilityNotice::KEY);
    }
}
