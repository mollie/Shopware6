<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Payment\Handler\SubscriptionAwareInterface;
use Mollie\Shopware\Component\Payment\MethodRemover\AbstractPaymentRemover;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionRemover extends AbstractPaymentRemover
{
    public function __construct(
        #[Autowire(service: SubscriptionLineItemsResolver::class)]
        private readonly SubscriptionLineItemsResolverInterface $lineItemsResolver,
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
    ) {
    }

    public function remove(PaymentMethodCollection $paymentMethods, string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $subscriptionSettings = $this->settingsService->getSubscriptionSettings($salesChannelContext->getSalesChannelId());
        if (! $subscriptionSettings->isEnabled()) {
            return $paymentMethods;
        }

        $lineItems = $this->lineItemsResolver->resolveLineItems($orderId, $salesChannelContext);
        if (! $this->lineItemAnalyzer->hasSubscriptionProduct($lineItems)) {
            return $paymentMethods;
        }

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodHandler = $this->paymentHandlerLocator->findByIdentifier($paymentMethod->getHandlerIdentifier());
            if (! $paymentMethodHandler instanceof SubscriptionAwareInterface) {
                $paymentMethods->remove($paymentMethod->getId());
            }
        }

        return $paymentMethods;
    }
}
