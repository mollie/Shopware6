<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: UpdateStatusScheduledTask::class)]
final class UpdateStatusTaskHandler extends ScheduledTaskHandler
{
    private UpdateStatusAction $action;

    /**
     * @param EntityRepository<EntityCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     */
    public function __construct(UpdateStatusAction $action, $scheduledTaskRepository, LoggerInterface $exceptionLogger)
    {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
        $this->action = $action;
    }

    public function run(): void
    {
        $result = $this->action->execute();
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
