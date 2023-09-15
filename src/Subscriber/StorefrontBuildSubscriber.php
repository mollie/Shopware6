<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontBuildSubscriber implements EventSubscriberInterface
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
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    /**
     * @param StorefrontRenderEvent $event
     * @throws \Exception
     * @return void
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $useJsValue = (int)$settings->isUseShopwareJavascript();
        $event->setParameter('mollie_javascript_use_shopware', $useJsValue);
    }
}
