<?php

namespace Kiener\MolliePayments\Repository\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class ScheduledTaskRepository implements ScheduledTaskRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $repoScheduledTasks;

    /**
     * @param EntityRepository $repoScheduledTasks
     */
    public function __construct($repoScheduledTasks)
    {
        $this->repoScheduledTasks = $repoScheduledTasks;
    }

    /**
     * @return EntityRepository
     */
    public function getRepository(): EntityRepository
    {
        return $this->repoScheduledTasks;
    }
}
