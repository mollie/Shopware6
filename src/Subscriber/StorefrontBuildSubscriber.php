<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontBuildSubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var StorefrontPluginRegistryInterface
     */
    private $pluginRegistry;

    /**
     * @var VersionCompare
     */
    private $versionCompare;


    /**
     * @param SettingsService $settingsService
     * @param string $shopwareVersion
     */
    public function __construct(SettingsService $settingsService, StorefrontPluginRegistryInterface $pluginRegistry, string $shopwareVersion)
    {
        $this->settingsService = $settingsService;
        $this->pluginRegistry = $pluginRegistry;
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

        $mollieJavascriptAlreadyExists = false;
        if($this->versionCompare->gte('6.6')) {
            $molliePayments = $this->pluginRegistry->getConfigurations()->getByTechnicalName('MolliePayments');
            $mollieJavascriptAlreadyExists = $molliePayments && ($molliePayments->getScriptFiles()->count() > 0);
        }
        $event->setParameter('mollie_javascript_already_exists', $mollieJavascriptAlreadyExists);
    }
}
