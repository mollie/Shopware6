<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cli;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Cli\RenewalReminderCommand;
use Mollie\Shopware\Component\Subscription\ReminderValidator;
use Mollie\Shopware\Component\Subscription\SubscriptionRenewalReminder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command exists so a merchant can trigger the renewal reminders from a cron job instead of
 * the Shopware queue. The exit code is what their cron watches, so it has to be honest.
 */
#[CoversClass(RenewalReminderCommand::class)]
final class RenewalReminderCommandTest extends TestCase
{
    public function testTheCommandIsCalledUnderItsMollieName(): void
    {
        $this->assertSame('mollie:subscriptions:renewal-reminder', $this->command()->getName());
    }

    public function testAFinishedRunReportsSuccessToTheCronJob(): void
    {
        $tester = new CommandTester($this->command());

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testTheNumberOfProcessedRemindersIsPrinted(): void
    {
        $tester = new CommandTester($this->command());

        $tester->execute([]);

        $this->assertStringContainsString('0 subscription renewal reminders processed', $tester->getDisplay());
    }

    /**
     * A cron job that keeps reporting success while nothing works is worse than a failing one, so
     * a broken run has to exit non-zero and say why.
     */
    public function testABrokenRunReportsFailureAndTheReason(): void
    {
        $subscriptionRepository = new FakeSubscriptionRepository();
        $subscriptionRepository->withSearchFailure(new \RuntimeException('database gone'));

        $tester = new CommandTester($this->command($subscriptionRepository));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('database gone', $tester->getDisplay());
    }

    private function command(?FakeSubscriptionRepository $subscriptionRepository = null): RenewalReminderCommand
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');

        $salesChannelRepository = new FakeSalesChannelRepository();
        $salesChannelRepository->add($salesChannel);

        $reminder = new SubscriptionRenewalReminder(
            $salesChannelRepository,
            $subscriptionRepository ?? new FakeSubscriptionRepository(),
            new FakeCustomerRepository(),
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true, reminderDays: 3)),
            new ReminderValidator(),
            new EventSpy(),
            new FakeLogger()
        );

        return new RenewalReminderCommand($reminder, new FakeLogger());
    }
}
