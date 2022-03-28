<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\ScheduledTask\RenewalReminder;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
     * @param EntityRepositoryInterface $scheduledTaskRepository
     * @param SubscriptionManager $subscriptionManager
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, SubscriptionManager $subscriptionManager, LoggerInterface $logger)
    {
        parent::__construct($scheduledTaskRepository);

        $this->subscriptionManager = $subscriptionManager;
        $this->logger = $logger;
    }

    /**
     * @return iterable
     */
    public static function getHandledMessages(): iterable
    {
        return [RenewalReminderTask::class];
    }

    /**
     *
     */
    public function run(): void
    {
        try {

            $context = Context::createDefaultContext();

            $this->subscriptionManager->remindSubscriptionRenewal($context);

        } catch (\Throwable $ex) {

            $this->logger->error(
                'Error when running Scheduled Task for Subscription Renewal Reminders. ' . $ex->getMessage()
            );
        }
    }

}
