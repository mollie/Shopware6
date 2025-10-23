<?php
declare(strict_types=1);


namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class ApiSettings extends Struct
{
    public const KEY_TEST_API_KEY = 'testApiKey';
    public const KEY_LIVE_API_KEY = 'liveApiKey';
    public const KEY_TEST_MODE = 'testMode';

    private string $testApiKey;
    private string $liveApiKey;
    private bool $testMode;

    /**
     * @param string $testApiKey
     * @param string $liveApiKey
     * @param bool $testMode
     */
    public function __construct(string $testApiKey, string $liveApiKey, bool $testMode)
    {
        $this->testApiKey = $testApiKey;
        $this->liveApiKey = $liveApiKey;
        $this->testMode = $testMode;
    }

    public static function createFromShopwareArray(array $settings): self
    {
        $testApiKey = $settings[self::KEY_TEST_API_KEY] ?? '';
        $liveApiKey = $settings[self::KEY_LIVE_API_KEY] ?? '';
        $testMode = $settings[self::KEY_TEST_MODE] ?? false;
        return new self($testApiKey, $liveApiKey, $testMode);
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
        return $this->testMode === true;
    }

    public function getApiKey(): string
    {
        if ($this->isTestMode()) {
            return $this->testApiKey;
        }
        return $this->liveApiKey;
    }
}