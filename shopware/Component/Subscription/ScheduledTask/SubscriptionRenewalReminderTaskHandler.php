<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\ScheduledTask;

use Mollie\Shopware\Component\Subscription\SubscriptionRenewalReminder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SubscriptionRenewalReminderTask::class)]
final class SubscriptionRenewalReminderTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        private readonly SubscriptionRenewalReminder $renewalReminder,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $this->logger);
    }

    public function run(): void
    {
        try {
            $count = $this->renewalReminder->remind(new Context(new SystemSource()));
            $this->logger->debug(sprintf('%d subscription renewal reminders processed', $count));
        } catch (\Throwable $exception) {
            $this->logger->error('Subscription renewal reminder scheduled task failed: ' . $exception->getMessage());
        }
    }

    /**
     * @return iterable<class-string>
     */
    public static function getHandledMessages(): iterable
    {
        return [SubscriptionRenewalReminderTask::class];
    }
}
