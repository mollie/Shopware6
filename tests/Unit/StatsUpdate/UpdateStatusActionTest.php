<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StatsUpdate;

use Mollie\Shopware\Component\StatusUpdate\UpdateStatusAction;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Fake\FakeWebhookRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(UpdateStatusAction::class)]
class UpdateStatusActionTest extends TestCase
{
    /**
     * This test verifies that no updates are reported when no open transactions exist.
     */
    public function testNothingToUpdate(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $webhookRoute = new FakeWebhookRoute();

        $action = new UpdateStatusAction($repository, $webhookRoute, new NullLogger());
        $result = $action->execute();

        $this->assertEquals(0, $result->getUpdated());
        $this->assertEmpty($webhookRoute->getNotifiedTransactionIds());
    }

    /**
     * This test verifies that one transaction is notified and counted when it is open.
     */
    public function testOneTransactionUpdated(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $repository->setMatchingIds('test123');

        $webhookRoute = new FakeWebhookRoute();

        $action = new UpdateStatusAction($repository, $webhookRoute, new NullLogger());
        $result = $action->execute();

        $this->assertEquals(1, $result->getUpdated());
        $this->assertEquals(['test123'], $webhookRoute->getNotifiedTransactionIds());
    }

    /**
     * This test verifies that a failing webhook notification is caught and the transaction
     * is not counted as updated.
     */
    public function testFailedNotificationIsSkipped(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $repository->setMatchingIds('tx-ok', 'tx-fail', 'tx-ok-2');

        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->addFailingTransactionId('tx-fail');

        $action = new UpdateStatusAction($repository, $webhookRoute, new NullLogger());
        $result = $action->execute();

        $this->assertEquals(2, $result->getUpdated());
    }
}
