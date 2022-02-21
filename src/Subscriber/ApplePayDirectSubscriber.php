<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ApplePayDirectSubscriber implements EventSubscriberInterface
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
     */
    public function onStorefrontRender(StorefrontRenderEvent $event)
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $applePayDirectEnabled = (bool)$settings->isEnableApplePayDirect();

        $event->setParameter('mollie_applepaydirect_enabled', $applePayDirectEnabled);
        $event->setParameter('mollie_applepaydirect_restrictions', $settings->getRestrictApplePayDirect());
    }

}
