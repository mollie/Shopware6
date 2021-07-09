<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Helper\ProfileHelper;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    /** @var SettingsService */
    private $settingsService;

    /** @var MollieApiClient */
    private $apiClient;

    /** @var array */
    private $profileIdStorage = [];

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
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
            $this->settingsService->setProfileId(null, $salesChannelId, $testMode);
            return;
        }

        $this->apiClient->setApiKey($value);

        $profile = ProfileHelper::getProfile($this->apiClient, new MollieSettingStruct());

        $this->settingsService->setProfileId($profile->id, $salesChannelId, $testMode);

        $this->profileIdStorage[$salesChannelId . $profileKey] = $profile->id;
    }

    /**
     * Why do we need to fix the profile ID?
     * When adding a key to the system config programmatically, even if there is no field for it in config.xml,
     * when saving the configuration in the administration, Shopware will also save those keys.
     * We need to fix the profile ID, because we fetch the new profile ID from Mollie and save it to the system config,
     * and then Shopware overwrites it with the old one afterwards.
     *
     * @param string $key
     * @param $value
     * @param string|null $salesChannelId
     * @param bool $testMode
     */
    private function fixProfileIdAfterChange(string $key, $value, ?string $salesChannelId, bool $testMode = false)
    {
        if (isset($this->profileIdStorage[$salesChannelId . $key])) {
            // If the old $value is the same as the new profile ID in storage, then dont set it again
            // Will end up in an endless loop otherwise.
            if ($this->profileIdStorage[$salesChannelId . $key] === $value) {
                return;
            }

            $this->settingsService->setProfileId($this->profileIdStorage[$salesChannelId . $key], $salesChannelId, $testMode);
        } else {
            // If we haven't stored the profile ID from Mollie, but we are getting a value here from the admin,
            // then we no longer need to store this key, so delete it.
            if ($value) {
                $this->settingsService->setProfileId(null, $salesChannelId, $testMode);
            }
        }
    }
}
