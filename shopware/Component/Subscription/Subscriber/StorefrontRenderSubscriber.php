<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StorefrontRenderSubscriber implements EventSubscriberInterface
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
        $settings = $this->settingsService->getSubscriptionSettings(
            $event->getSalesChannelContext()->getSalesChannelId()
        );

        $event->setParameter('mollie_subscriptions_enabled', $settings->isEnabled());
        $event->setParameter('mollie_subscriptions_show_indicator', $settings->isShowIndicator());
    }
}
