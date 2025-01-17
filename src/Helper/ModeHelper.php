<?php


namespace Kiener\MolliePayments\Helper;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\MollieApiClient;

class ModeHelper
{
    /**
     * @param array<mixed> $data
     * @param MollieApiClient $apiClient
     * @param MollieSettingStruct $settings
     */
    public static function addModeToData(array &$data, MollieApiClient $apiClient, MollieSettingStruct $settings): void
    {
        if ($apiClient->usesOAuth() === true && $settings->isTestMode() === true) {
            $data['testmode'] = true;
        }

        if ($apiClient->usesOAuth() === false) {
            if ($settings->isTestMode() === true) {
                $data['mode'] = 'test';
            } else {
                $data['mode'] = 'live';
            }
        }
    }
}
