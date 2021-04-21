<?php


namespace Kiener\MolliePayments\Helper;


use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Profile;

class ProfileHelper
{
    /**
     * Returns the current profile for Mollie.
     *
     * @param array               $data
     * @param MollieApiClient     $apiClient
     * @param MollieSettingStruct $settings
     */
    public static function addProfileToData(array &$data, MollieApiClient $apiClient, MollieSettingStruct $settings): void
    {
        $profile = self::getProfile($apiClient, $settings);

        if ($profile !== null && isset($profile->id)) {
            $data['profileId'] = $profile->id;
        }
    }

    /**
     * Returns the current profile for Mollie's API.
     *
     * @param MollieApiClient     $apiClient
     * @param MollieSettingStruct $settings
     *
     * @return Profile|null
     */
    public static function getProfile(MollieApiClient $apiClient, MollieSettingStruct $settings): ?Profile
    {
        /** @var Profile $profile */
        $profile = null;

        try {
            if ($apiClient->usesOAuth() === false) {
                $profile = $apiClient->profiles->getCurrent();

                if ($settings->getProfileId() !== null) {
                    $profile = $apiClient->profiles->get($settings->getProfileId());
                }
            }

            if ($apiClient->usesOAuth() === true) {
                if ($apiClient->profiles->page()->count > 0) {
                    $offset = $apiClient->profiles->page()->count - 1;

                    if ($apiClient->profiles->page()->offsetExists($offset)) {
                        $profile = $apiClient->profiles->page()->offsetGet($offset);
                    } else {
                        $profile = $apiClient->profiles->page()->offsetGet(0);
                    }
                }

                if ($settings->getProfileId() !== null) {
                    $profile = $apiClient->profiles->get($settings->getProfileId());
                }
            }
        } catch (ApiException $e) {
            //
        }

        return $profile;
    }
}