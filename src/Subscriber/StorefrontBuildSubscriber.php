<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Compatibility\VersionCompare;
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
     * @var VersionCompare
     */
    private $versionCompare;


    /**
     * @param SettingsService $settingsService
     * @param string $shopwareVersion
     */
    public function __construct(SettingsService $settingsService, string $shopwareVersion)
    {
        $this->settingsService = $settingsService;
        $this->versionCompare = new VersionCompare($shopwareVersion);
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
        $event->setParameter('mollie_javascript_check_duplicate', $this->versionCompare->gte('6.6'));
    }
}
