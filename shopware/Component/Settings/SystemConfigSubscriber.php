<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Service\ApplePayDomainVerificationService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SystemConfigSubscriber implements EventSubscriberInterface
{
    private const KEY_PROFILE_ID = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_PROFILE_ID;

    private const KEY_APPLE_PAY_DIRECT_ENABLED = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApplePaySettings::KEY_APPLE_PAY_DIRECT_ENABLED;

    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        private SystemConfigService $systemConfigService,
        private ApplePayDomainVerificationService $domainVerification,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => [
                ['updateProfileId'],
                ['downloadApplePayDomainAssociationFile'],
            ],
        ];
    }

    public function updateProfileId(SystemConfigChangedEvent $event): void
    {
        if (! $this->hasNeededConfig($event->getKey())) {
            return;
        }
        $salesChannelId = $event->getSalesChannelId();
        $profileId = null;
        try {
            $profile = $this->mollieGateway->getCurrentProfile($salesChannelId);

            $this->logger->info('Mollie API Config was changed, a new Profile ID was set', [
                'profileId' => $profileId,
                'salesChannelId' => $salesChannelId,
            ]);
            $this->systemConfigService->set(self::KEY_PROFILE_ID, $profile->getId(), $salesChannelId);
        } catch (\Throwable $exception) {
            $this->logger->warning('Mollie API Config was changed, profile ID was deleted', [
                'salesChannelId' => $salesChannelId,
                'message' => $exception->getMessage(),
            ]);
            $this->systemConfigService->set(self::KEY_PROFILE_ID, null, $salesChannelId);
        }
    }

    public function downloadApplePayDomainAssociationFile(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== self::KEY_APPLE_PAY_DIRECT_ENABLED) {
            return;
        }

        if (! $event->getValue()) {
            return;
        }

        try {
            $downloaded = $this->domainVerification->downloadDomainAssociationFile();

            if (! $downloaded) {
                $this->logger->warning('Apple Pay was enabled but the domain verification file could not be downloaded from Mollie', [
                    'salesChannelId' => $event->getSalesChannelId(),
                ]);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to download Apple Pay domain verification file after enabling Apple Pay', [
                'salesChannelId' => $event->getSalesChannelId(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function hasNeededConfig(string $key): bool
    {
        $testModeConfigKey = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_MODE;
        $liveApiConfigKey = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_LIVE_API_KEY;
        $testApiConfigKey = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_API_KEY;
        $neededKeys = [
            $testApiConfigKey,
            $liveApiConfigKey,
            $testModeConfigKey,
        ];

        return in_array($key, $neededKeys, true);
    }
}
