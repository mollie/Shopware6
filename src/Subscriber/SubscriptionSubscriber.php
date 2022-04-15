<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Product\ProductPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SubscriptionSubscriber implements EventSubscriberInterface
{

    /**
     * @var SettingsService
     */
    private $settingsService;


    /**
     * @param SettingsService $settingsService
     */
    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    /**
     * @param StorefrontRenderEvent $event
     */
    public function onStorefrontRender(StorefrontRenderEvent $event)
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $event->setParameter('mollie_subscriptions_enabled_beta', $settings->isSubscriptionsEnableBeta());
        $event->setParameter('mollie_subscriptions_indicator_enabled', $settings->isSubscriptionsShowIndicator());
    }

}
