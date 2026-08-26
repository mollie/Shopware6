<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Service\ApplePayDomainVerificationService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use Mollie\Shopware\Component\Settings\SystemConfigSubscriber;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

#[CoversClass(SystemConfigSubscriber::class)]
final class SystemConfigSubscriberTest extends TestCase
{
    private const PROFILE_ID_KEY = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_PROFILE_ID;

    private const APPLE_PAY_KEY = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApplePaySettings::KEY_APPLE_PAY_DIRECT_ENABLED;

    private const SALES_CHANNEL = 'sales-channel-1';

    private StaticSystemConfigService $systemConfigService;

    private FakeGateway $gateway;

    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->gateway = new FakeGateway();
        $this->logger = new FakeLogger();
    }

    public function testSubscribesToSystemConfigChangedEvent(): void
    {
        $events = SystemConfigSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(SystemConfigChangedEvent::class, $events);
    }

    public function testHandlesProfileIdAndApplePayDownload(): void
    {
        $listeners = SystemConfigSubscriber::getSubscribedEvents()[SystemConfigChangedEvent::class];

        $methods = array_map(static function (array $listener): string {
            return $listener[0];
        }, $listeners);

        self::assertContains('updateProfileId', $methods);
        self::assertContains('downloadApplePayDomainAssociationFile', $methods);
    }

    #[DataProvider('apiConfigKeys')]
    public function testProfileIdIsRefreshedAfterAnApiKeyChange(string $changedKey): void
    {
        $subscriber = $this->createSubscriber();

        $subscriber->updateProfileId(new SystemConfigChangedEvent($changedKey, 'new-value', self::SALES_CHANNEL));

        self::assertSame('fake_profile', $this->systemConfigService->get(self::PROFILE_ID_KEY, self::SALES_CHANNEL));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function apiConfigKeys(): array
    {
        return [
            'test api key' => [SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_API_KEY],
            'live api key' => [SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_LIVE_API_KEY],
            'test mode' => [SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_MODE],
        ];
    }

    public function testAnUnrelatedConfigChangeLeavesTheProfileIdAlone(): void
    {
        $this->systemConfigService->set(self::PROFILE_ID_KEY, 'pfl_existing', self::SALES_CHANNEL);
        $subscriber = $this->createSubscriber();

        $subscriber->updateProfileId(new SystemConfigChangedEvent(self::APPLE_PAY_KEY, true, self::SALES_CHANNEL));

        self::assertSame('pfl_existing', $this->systemConfigService->get(self::PROFILE_ID_KEY, self::SALES_CHANNEL));
        self::assertSame(0, $this->gateway->getCallCount('getCurrentProfile'));
    }

    public function testProfileIdIsClearedWhenMollieRejectsTheNewApiKey(): void
    {
        $this->systemConfigService->set(self::PROFILE_ID_KEY, 'pfl_existing', self::SALES_CHANNEL);
        $this->gateway->withProfileFailure(new \RuntimeException('Invalid API key'));
        $subscriber = $this->createSubscriber();

        $subscriber->updateProfileId(new SystemConfigChangedEvent(
            SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_API_KEY,
            'broken',
            self::SALES_CHANNEL
        ));

        self::assertNull($this->systemConfigService->get(self::PROFILE_ID_KEY, self::SALES_CHANNEL));
        self::assertTrue($this->logger->hasRecordThatContains('warning', 'profile ID was deleted'));
    }

    public function testDomainVerificationFileIsDownloadedWhenApplePayIsEnabled(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $subscriber = $this->createSubscriber($this->domainVerification($filesystem, new GuzzleResponse(200, [], 'verification-content')));

        $subscriber->downloadApplePayDomainAssociationFile(new SystemConfigChangedEvent(self::APPLE_PAY_KEY, true, self::SALES_CHANNEL));

        self::assertTrue($filesystem->has(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    public function testNothingIsDownloadedWhenApplePayIsSwitchedOff(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $subscriber = $this->createSubscriber($this->domainVerification($filesystem, new GuzzleResponse(200, [], 'verification-content')));

        $subscriber->downloadApplePayDomainAssociationFile(new SystemConfigChangedEvent(self::APPLE_PAY_KEY, false, self::SALES_CHANNEL));

        self::assertFalse($filesystem->has(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    public function testNothingIsDownloadedForAnUnrelatedConfigChange(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $subscriber = $this->createSubscriber($this->domainVerification($filesystem, new GuzzleResponse(200, [], 'verification-content')));

        $subscriber->downloadApplePayDomainAssociationFile(new SystemConfigChangedEvent(self::PROFILE_ID_KEY, 'pfl_123', self::SALES_CHANNEL));

        self::assertFalse($filesystem->has(ApplePayDomainVerificationService::LOCAL_FILE));
    }

    public function testAnUnavailableDomainVerificationFileIsLoggedAsAWarning(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $subscriber = $this->createSubscriber($this->domainVerification($filesystem, new GuzzleResponse(503)));

        $subscriber->downloadApplePayDomainAssociationFile(new SystemConfigChangedEvent(self::APPLE_PAY_KEY, true, self::SALES_CHANNEL));

        self::assertTrue($this->logger->hasRecordThatContains('warning', 'could not be downloaded from Mollie'));
    }

    public function testAFailingDownloadIsLoggedAsAnError(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $subscriber = $this->createSubscriber($this->domainVerification($filesystem, new \RuntimeException('mollie.com not reachable')));

        $subscriber->downloadApplePayDomainAssociationFile(new SystemConfigChangedEvent(self::APPLE_PAY_KEY, true, self::SALES_CHANNEL));

        self::assertTrue($this->logger->hasRecordThatContains('error', 'Failed to download Apple Pay domain verification file'));
    }

    private function createSubscriber(?ApplePayDomainVerificationService $domainVerification = null): SystemConfigSubscriber
    {
        return new SystemConfigSubscriber(
            $this->gateway,
            $this->systemConfigService,
            $domainVerification ?? $this->domainVerification(new Filesystem(new InMemoryFilesystemAdapter()), new GuzzleResponse(200, [], 'verification-content')),
            $this->logger,
        );
    }

    private function domainVerification(Filesystem $filesystem, GuzzleResponse|\Throwable $answer): ApplePayDomainVerificationService
    {
        $httpClient = new Client(['handler' => HandlerStack::create(new MockHandler([$answer]))]);

        return new ApplePayDomainVerificationService($filesystem, $httpClient);
    }
}
