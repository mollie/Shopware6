<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpiredSessionPaymentTaskHandler;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeScheduledTaskRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(ExpiredSessionPaymentTaskHandler::class)]
final class ExpiredSessionPaymentTaskHandlerTest extends TestCase
{
    private FakeSessionPaymentRepository $sessionPaymentRepository;
    private FakeSalesChannelRepository $salesChannelRepository;
    private FakeSettingsService $settingsService;

    protected function setUp(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository();
        $this->salesChannelRepository = new FakeSalesChannelRepository();
        $this->settingsService = new FakeSettingsService();
    }

    public function testNothingIsDeletedWhenNoSalesChannelOffersExpressComponents(): void
    {
        $this->addSalesChannel('sales-channel-1', true);

        $this->createHandler()->run();

        $this->assertSame(0, $this->sessionPaymentRepository->getDeleteExpiredCalls());
    }

    public function testExpiredSessionsAreDeletedWhenOneSalesChannelOffersExpressComponents(): void
    {
        $this->addSalesChannel('sales-channel-1', true);
        $this->addSalesChannel('sales-channel-2', true);
        $this->settingsService->setExpressComponentsSettingsForSalesChannel('sales-channel-2', new ExpressComponentsSettings(true));

        $this->createHandler()->run();

        $this->assertSame(1, $this->sessionPaymentRepository->getDeleteExpiredCalls());
    }

    public function testAnInactiveSalesChannelDoesNotTriggerTheCleanup(): void
    {
        $this->addSalesChannel('sales-channel-1', false);
        $this->settingsService->setExpressComponentsSettingsForSalesChannel('sales-channel-1', new ExpressComponentsSettings(true));

        $this->createHandler()->run();

        $this->assertSame(0, $this->sessionPaymentRepository->getDeleteExpiredCalls());
    }

    private function createHandler(): ExpiredSessionPaymentTaskHandler
    {
        return new ExpiredSessionPaymentTaskHandler(
            new FakeScheduledTaskRepository(),
            $this->sessionPaymentRepository,
            $this->salesChannelRepository,
            $this->settingsService,
            new NullLogger()
        );
    }

    private function addSalesChannel(string $salesChannelId, bool $active): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setUniqueIdentifier($salesChannelId);
        $salesChannel->setActive($active);

        $this->salesChannelRepository->add($salesChannel);
    }
}
