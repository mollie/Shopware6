<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate\Subscriber;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MandateSidebarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();

        $creditCardSettings = $this->settingsService->getCreditCardSettings($salesChannelId);
        $paymentSettings = $this->settingsService->getPaymentSettings($salesChannelId);

        $event->setParameter('enable_credit_card_components', $creditCardSettings->isCreditCardComponentsEnabled());
        $event->setParameter('enable_one_click_payments', $paymentSettings->isOneClickPayment());
    }
}
