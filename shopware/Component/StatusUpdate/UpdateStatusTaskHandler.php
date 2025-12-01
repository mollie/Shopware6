<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: UpdateStatusScheduledTask::class)]
final class UpdateStatusTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     */
    public function __construct(private UpdateStatusAction $action,
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        LoggerInterface $exceptionLogger
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->action->execute();
    }

    /**
     * @return iterable<mixed>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            UpdateStatusScheduledTask::class,
        ];
    }
}
