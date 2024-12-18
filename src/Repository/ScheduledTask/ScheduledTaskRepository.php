<?php

namespace Kiener\MolliePayments\Repository\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;

class ScheduledTaskRepository implements ScheduledTaskRepositoryInterface
{
    /**
     * @var EntityRepository<ScheduledTaskCollection>
     */
    private $repoScheduledTasks;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $repoScheduledTasks
     */
    public function __construct($repoScheduledTasks)
    {
        $this->repoScheduledTasks = $repoScheduledTasks;
    }

    /**
     * @return EntityRepository<ScheduledTaskCollection>
     */
    public function getRepository(): EntityRepository
    {
        return $this->repoScheduledTasks;
    }
}
