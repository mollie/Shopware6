<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StatusUpdate;

use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\StatusUpdate\UpdateStatusAction;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeWebhookRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(UpdateStatusAction::class)]
final class UpdateStatusActionTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';
    private const OTHER_SALES_CHANNEL_ID = 'other-sales-channel-id';

    private FakeOrderTransactionRepository $repository;
    private FakeSalesChannelRepository $salesChannelRepository;
    private FakeSettingsService $settingsService;
    private FakeWebhookRoute $webhookRoute;
    private UpdateStatusAction $action;

    protected function setUp(): void
    {
        $this->repository = new FakeOrderTransactionRepository();
        $this->salesChannelRepository = new FakeSalesChannelRepository();
        $this->settingsService = new FakeSettingsService();
        $this->webhookRoute = new FakeWebhookRoute();

        $this->action = new UpdateStatusAction(
            $this->repository,
            $this->webhookRoute,
            $this->salesChannelRepository,
            $this->settingsService,
            new NullLogger()
        );
    }

    /**
     * This test verifies that nothing is searched or notified as long as no sales channel has
     * the automatic status update enabled.
     */
    public function testNothingIsNotifiedWithoutAnEnabledSalesChannel(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, true);
        $this->repository->setMatchingIds('test123');

        $result = $this->action->execute();

        $this->assertEquals(0, $result->getUpdated());
        $this->assertSame([], $this->repository->getRequestedSalesChannelIds());
        $this->assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    /**
     * This test verifies that no updates are reported when an enabled sales channel has no open
     * transactions.
     */
    public function testNothingIsUpdatedWithoutOpenTransactions(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, true);
        $this->enableStatusUpdate(self::SALES_CHANNEL_ID);

        $result = $this->action->execute();

        $this->assertEquals(0, $result->getUpdated());
        $this->assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    /**
     * This test verifies that one transaction is notified and counted when it is open in an
     * enabled sales channel.
     */
    public function testOneTransactionUpdated(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, true);
        $this->enableStatusUpdate(self::SALES_CHANNEL_ID);
        $this->repository->setMatchingIds('test123');

        $result = $this->action->execute();

        $this->assertEquals(1, $result->getUpdated());
        $this->assertSame(['test123'], $this->webhookRoute->getNotifiedTransactionIds());
    }

    /**
     * This test verifies that a sales channel without the automatic status update is not
     * searched for open transactions at all.
     */
    public function testSalesChannelWithoutStatusUpdateIsNotSearched(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, true);
        $this->addSalesChannel(self::OTHER_SALES_CHANNEL_ID, true);
        $this->enableStatusUpdate(self::OTHER_SALES_CHANNEL_ID);

        $this->action->execute();

        $this->assertSame([self::OTHER_SALES_CHANNEL_ID], $this->repository->getRequestedSalesChannelIds());
    }

    /**
     * This test verifies that an inactive sales channel is not searched, even with the automatic
     * status update enabled.
     */
    public function testInactiveSalesChannelIsNotSearched(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, false);
        $this->enableStatusUpdate(self::SALES_CHANNEL_ID);

        $this->action->execute();

        $this->assertSame([], $this->repository->getRequestedSalesChannelIds());
    }

    /**
     * This test verifies that the open transactions of every enabled sales channel are notified.
     */
    public function testTransactionsOfAllEnabledSalesChannelsAreNotified(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, true);
        $this->addSalesChannel(self::OTHER_SALES_CHANNEL_ID, true);
        $this->enableStatusUpdate(self::SALES_CHANNEL_ID);
        $this->enableStatusUpdate(self::OTHER_SALES_CHANNEL_ID);
        $this->repository->setMatchingIdsForSalesChannel(self::SALES_CHANNEL_ID, 'tx-first');
        $this->repository->setMatchingIdsForSalesChannel(self::OTHER_SALES_CHANNEL_ID, 'tx-second');

        $result = $this->action->execute();

        $this->assertEquals(2, $result->getUpdated());
        $this->assertSame(['tx-first', 'tx-second'], $this->webhookRoute->getNotifiedTransactionIds());
    }

    /**
     * This test verifies that a failing webhook notification is caught and the transaction
     * is not counted as updated.
     */
    public function testFailedNotificationIsSkipped(): void
    {
        $this->addSalesChannel(self::SALES_CHANNEL_ID, true);
        $this->enableStatusUpdate(self::SALES_CHANNEL_ID);
        $this->repository->setMatchingIds('tx-ok', 'tx-fail', 'tx-ok-2');
        $this->webhookRoute->addFailingTransactionId('tx-fail');

        $result = $this->action->execute();

        $this->assertEquals(2, $result->getUpdated());
        $this->assertSame(['tx-ok', 'tx-ok-2'], $this->webhookRoute->getNotifiedTransactionIds());
    }

    private function addSalesChannel(string $salesChannelId, bool $active): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setUniqueIdentifier($salesChannelId);
        $salesChannel->setActive($active);

        $this->salesChannelRepository->add($salesChannel);
    }

    private function enableStatusUpdate(string $salesChannelId): void
    {
        $this->settingsService->setPaymentSettingsForSalesChannel($salesChannelId, new PaymentSettings('', 0, automaticStatusUpdate: true));
    }
}
