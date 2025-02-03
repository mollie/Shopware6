<?php declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\Subscription\RenewalReminder;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

abstract class AbstractRenewalReminderTaskHandler extends ScheduledTaskHandler
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
     * @param EntityRepository $scheduledTaskRepository
     * @param SubscriptionManager $subscriptionManager
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepository $scheduledTaskRepository, SubscriptionManager $subscriptionManager, LoggerInterface $logger)
    {
        /** @phpstan-ignore-next-line  */
        parent::__construct($scheduledTaskRepository, $logger);

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
            $context = new Context(new SystemSource());

            $remindedCount = $this->subscriptionManager->remindSubscriptionRenewal($context);

            $this->logger->debug($remindedCount . ' subscriptions renewal reminders have been processed successfully!');
        } catch (\Throwable $ex) {
            $this->logger->error(
                'Error when running Scheduled Task for Subscription Renewal Reminders. ' . $ex->getMessage()
            );
        }
    }
}
