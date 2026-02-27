<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Mollie\Shopware\Component\Mollie\Mode;
use Shopware\Core\Framework\Struct\Struct;

final class ApiSettings extends Struct
{
    public const KEY_TEST_API_KEY = 'testApiKey';
    public const KEY_LIVE_API_KEY = 'liveApiKey';
    public const KEY_TEST_MODE = 'testMode';

    public const KEY_PROFILE_ID = 'profileId';

    public function __construct(private string $testApiKey, private string $liveApiKey, private Mode $mode,private string $profileId)
    {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $testApiKey = $settings[self::KEY_TEST_API_KEY] ?? '';
        $liveApiKey = $settings[self::KEY_LIVE_API_KEY] ?? '';
        $profileId = $settings[self::KEY_PROFILE_ID] ?? '';
        $testMode = $settings[self::KEY_TEST_MODE] ?? 'true';

        $mode = (bool) $testMode === true ? Mode::TEST : Mode::LIVE;

        return new self($testApiKey, $liveApiKey, $mode,$profileId);
    }

    public function getTestApiKey(): string
    {
        return $this->testApiKey;
    }

    public function getLiveApiKey(): string
    {
        return $this->liveApiKey;
    }

    public function isTestMode(): bool
    {
        return $this->mode === Mode::TEST;
    }

    public function getApiKey(): string
    {
        if ($this->isTestMode()) {
            return $this->testApiKey;
        }

        return $this->liveApiKey;
    }

    public function getProfileId(): string
    {
        return $this->profileId;
    }

    public function getMode(): Mode
    {
        return $this->mode;
    }
}
