<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontBuildSubscriber implements EventSubscriberInterface
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /** @phpstan-ignore class.notFound */
    /**
     * @var StorefrontPluginRegistry|StorefrontPluginRegistryInterface
     */
    private $pluginRegistry;

    /**
     * @var VersionCompare
     */
    private $versionCompare;

    /** @phpstan-ignore class.notFound */
    /** @param StorefrontPluginRegistry|StorefrontPluginRegistryInterface $pluginRegistry */
    public function __construct(SettingsService $settingsService, $pluginRegistry, VersionCompare $versionCompare)
    {
        $this->settingsService = $settingsService;
        $this->pluginRegistry = $pluginRegistry;
        $this->versionCompare = $versionCompare;
    }

    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    /**
     * @throws \Exception
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $useJsValue = (int) $settings->isUseShopwareJavascript();
        $event->setParameter('mollie_javascript_use_shopware', $useJsValue);

        $mollieJavascriptAlreadyExists = false;

        if ($this->versionCompare->gte('6.6')) {
            /** @phpstan-ignore class.notFound */
            $plugin = $this->pluginRegistry->getConfigurations()->getByTechnicalName('MolliePayments');

            if ($plugin instanceof StorefrontPluginConfiguration) {
                $scriptFiles = $plugin->getScriptFiles();

                if ($useJsValue === 0) {
                    $scriptFiles->remove(0);
                }
                $mollieJavascriptAlreadyExists = $scriptFiles->count() > 0;
            }
        }

        $event->setParameter('mollie_javascript_already_exists', $mollieJavascriptAlreadyExists);
    }
}
