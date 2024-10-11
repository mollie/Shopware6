<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService implements PluginSettingsServiceInterface
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config';
    private const SYSTEM_CORE_LOGIN_REGISTRATION_CONFIG_DOMAIN = 'core.loginRegistration';
    private const SYSTEM_CORE_CART_CONFIG_DOMAIN = 'core.cart';

    private const PHONE_NUMBER_FIELD_REQUIRED = 'phoneNumberFieldRequired';

    private const PHONE_NUMBER_FIELD = 'showPhoneNumberField';

    private const REQUIRE_DATA_PROTECTION = 'requireDataProtectionCheckbox';

    private const PAYMENT_FINALIZE_TRANSACTION_TIME = 'paymentFinalizeTransactionTime';
    const LIVE_API_KEY = 'liveApiKey';
    const TEST_API_KEY = 'testApiKey';
    const LIVE_PROFILE_ID = 'liveProfileId';
    const TEST_PROFILE_ID = 'testProfileId';

    /**
     * @var SystemConfigService
     */
    protected $systemConfigService;

    /**
     *
     * @var SalesChannelRepositoryInterface
     */
    private $repoSalesChannels;

    /**
     * @var string
     */
    private $envShopDomain;

    /**
     * @var string
     */
    private $envDevMode;

    /**
     * @var string
     */
    private $envCypressMode;
    private PayPalExpressConfig $payPalExpressConfig;

    /**
     * @var array<string,MollieSettingStruct>
     */
    private array $cachedStructs = [];

    /**
     * @param SystemConfigService $systemConfigService
     * @param SalesChannelRepositoryInterface $repoSalesChannels
     * @param ?string $envShopDomain
     * @param ?string $envDevMode
     * @param ?string $envCypressMode
     */
    public function __construct(SystemConfigService $systemConfigService, SalesChannelRepositoryInterface $repoSalesChannels, PayPalExpressConfig $payPalExpressConfig, ?string $envShopDomain, ?string $envDevMode, ?string $envCypressMode)
    {
        $this->systemConfigService = $systemConfigService;
        $this->repoSalesChannels = $repoSalesChannels;

        $this->envShopDomain = (string)$envShopDomain;
        $this->envDevMode = (string)$envDevMode;
        $this->envCypressMode = (string)$envCypressMode;
        $this->payPalExpressConfig = $payPalExpressConfig;
    }

    /**
     * Get Mollie settings from configuration.
     *
     * @param null|string $salesChannelId
     * @return MollieSettingStruct
     */
    public function getSettings(?string $salesChannelId = null): MollieSettingStruct
    {
        $cacheKey = $salesChannelId ?? 'all';

        if (isset($this->cachedStructs[$cacheKey])) {
            return $this->cachedStructs[$cacheKey];
        }
        $structData = [];
        /** @var array<mixed> $systemConfigData */
        $systemConfigData = $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN, $salesChannelId);

        if (count($systemConfigData) !== 0) {
            foreach ($systemConfigData as $key => $value) {
                if (stripos($key, self::SYSTEM_CONFIG_DOMAIN) !== false) {
                    $structData[substr($key, strlen(self::SYSTEM_CONFIG_DOMAIN))] = $value;
                } else {
                    $structData[$key] = $value;
                }
            }
        }

        /** @var array<mixed> $coreSettings */
        $coreSettings = $this->systemConfigService->get(self::SYSTEM_CORE_LOGIN_REGISTRATION_CONFIG_DOMAIN, $salesChannelId);

        $structData[self::PHONE_NUMBER_FIELD_REQUIRED] = $coreSettings[self::PHONE_NUMBER_FIELD_REQUIRED] ?? false;

        $structData[self::PHONE_NUMBER_FIELD] = $coreSettings[self::PHONE_NUMBER_FIELD] ?? false;

        $structData[self::REQUIRE_DATA_PROTECTION] = $coreSettings[self::REQUIRE_DATA_PROTECTION] ?? false;

        /** @var array<mixed> $cartSettings */
        $cartSettings = $this->systemConfigService->get(self::SYSTEM_CORE_CART_CONFIG_DOMAIN, $salesChannelId);
        $structData[self::PAYMENT_FINALIZE_TRANSACTION_TIME] = $cartSettings[self::PAYMENT_FINALIZE_TRANSACTION_TIME] ?? 1800;

        /**
         * TODO: remove this when we move to config
         */
        if ($this->payPalExpressConfig->isEnabled()) {
            $structData = $this->payPalExpressConfig->assign($structData);
        }


        $this->cachedStructs[$cacheKey] = (new MollieSettingStruct())->assign($structData);

        return $this->cachedStructs[$cacheKey];
    }

    /**
     * Gets all configurations of all sales channels.
     * Every sales channel will be a separate entry in the array.
     *
     * @param Context $context
     * @return array<string, MollieSettingStruct>
     */
    public function getAllSalesChannelSettings(Context $context): array
    {
        $allConfigs = [];

        /** @var string[] $resultIDs */
        $resultIDs = $this->repoSalesChannels->searchIds(new Criteria(), $context)->getIds();

        foreach ($resultIDs as $scID) {
            $allConfigs[(string)$scID] = $this->getSettings((string)$scID);
        }

        return $allConfigs;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param null|string $salesChannelId
     */
    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . '.' . $key, $value, $salesChannelId);
    }

    /**
     * @param string $key
     * @param null|string $salesChannelId
     */
    public function delete(string $key, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->delete(self::SYSTEM_CONFIG_DOMAIN . '.' . $key, $salesChannelId);
    }

    /**
     * @param null|string $profileId
     * @param null|string $salesChannelId
     * @param bool $testMode
     */
    public function setProfileId(?string $profileId, ?string $salesChannelId = null, bool $testMode = false): void
    {
        $key = $testMode ? self::TEST_PROFILE_ID : self::LIVE_PROFILE_ID;

        if (! is_null($profileId)) {
            $this->set($key, $profileId, $salesChannelId);
        } else {
            $this->delete($key, $salesChannelId);
        }
    }

    /**
     * Gets the custom shop domain from the .env file.
     * This can be used for local NGROK approaches, or also
     * if you want to use a dedicated domain as the webhook endpoint
     * for your Mollie payments.
     * @return string
     */
    public function getEnvMollieShopDomain(): string
    {
        return trim($this->envShopDomain);
    }

    /**
     * This turns on the DEV mode in the plugin.
     * We try to always implement things as close to production as possible,
     * but sometimes we need a DEV mode ;).
     * @return bool
     */
    public function getEnvMollieDevMode(): bool
    {
        $devMode = trim($this->envDevMode);
        return ($devMode === '1');
    }

    /**
     * @return bool
     */
    public function getMollieCypressMode(): bool
    {
        $devMode = trim($this->envCypressMode);
        return ($devMode === '1');
    }
}
