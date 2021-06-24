<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Helper\ProfileHelper;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    /** @var SystemConfigService */
    private $configService;

    /** @var MollieApiClient */
    private $apiClient;

    /** @var array */
    private $profileIdStorage = [];


    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
        $this->apiClient = new MollieApiClient();
    }

    public static function getSubscribedEvents()
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event)
    {
        if (in_array($event->getKey(), [
            SettingsService::SYSTEM_CONFIG_DOMAIN . "liveProfileId",
            SettingsService::SYSTEM_CONFIG_DOMAIN . "testProfileId",
        ])) {
            $this->fixProfileIdAfterChange(
                $event->getKey(),
                $event->getValue(),
                $event->getSalesChannelId(),
                strpos($event->getKey(), 'testProfileId') !== false
            );
        }

        if (in_array($event->getKey(), [
            SettingsService::SYSTEM_CONFIG_DOMAIN . "liveApiKey",
            SettingsService::SYSTEM_CONFIG_DOMAIN . "testApiKey",
        ])) {
            $this->fetchProfileIdForApiKey(
                $event->getValue(),
                $event->getSalesChannelId(),
                strpos($event->getKey(), 'testApiKey') !== false
            );
        }
    }

    private function fetchProfileIdForApiKey($value, ?string $salesChannelId, bool $testMode = false)
    {
        $profileKey = SettingsService::SYSTEM_CONFIG_DOMAIN . ($testMode ? 'test' : 'live') . 'ProfileId';

        if (is_null($value)) {
            // If this api key has been "deleted", also remove the profile ID.
            $this->configService->delete($profileKey, $salesChannelId);

            return;
        }

        $this->apiClient->setApiKey($value);

        $profile = ProfileHelper::getProfile($this->apiClient, new MollieSettingStruct());

        $this->profileIdStorage[$salesChannelId . $profileKey] = $profile->id;

        $this->configService->set(
            $profileKey,
            $profile->id,
            $salesChannelId
        );
    }

    private function fixProfileIdAfterChange(string $key, $value, ?string $salesChannelId)
    {
        if(isset($this->profileIdStorage[$salesChannelId . $key])) {
            // If the old $value is the same as the new profile ID in storage, then dont set it again
            // Will end up in an endless loop otherwise.
            if($this->profileIdStorage[$salesChannelId . $key] === $value) {
                return;
            }

            $this->configService->set(
                $key,
                $this->profileIdStorage[$salesChannelId . $key],
                $salesChannelId
            );
        } else {
            // If we haven't stored the profile ID from Mollie, but we are getting a value here from the admin,
            // then we no longer need to store this key, so delete it.
            if($value) {
                $this->configService->delete($key, $salesChannelId);
            }
        }
    }
}
