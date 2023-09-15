<?php declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\Subscription\RenewalReminder;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Repository\ScheduledTask\ScheduledTaskRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class RenewalReminderTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ScheduledTaskRepositoryInterface $scheduledTaskRepository
     * @param SubscriptionManager $subscriptionManager
     * @param LoggerInterface $logger
     */
    public function __construct(ScheduledTaskRepositoryInterface $scheduledTaskRepository, SubscriptionManager $subscriptionManager, LoggerInterface $logger)
    {
        parent::__construct($scheduledTaskRepository->getRepository());

        $this->subscriptionManager = $subscriptionManager;
        $this->logger = $logger;
    }

    /**
     * @return iterable<mixed>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            RenewalReminderTask::class,
            RenewalReminderTaskDev::class,
        ];
    }

    /**
     *
     */
    public function run(): void
    {
        try {
            $this->logger->info('Starting Subscription Renewal Reminder from Scheduled Tasks.');

            $context = Context::createDefaultContext();

            $remindedCount = $this->subscriptionManager->remindSubscriptionRenewal($context);

            $this->logger->debug($remindedCount . ' subscriptions renewal reminders have been processed successfully!');
        } catch (\Throwable $ex) {
            $this->logger->error(
                'Error when running Scheduled Task for Subscription Renewal Reminders. ' . $ex->getMessage()
            );
        }
    }
}
