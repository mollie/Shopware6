<?php

namespace Kiener\MolliePayments\Repository\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;

interface ScheduledTaskRepositoryInterface
{
    /**
     * @return EntityRepository<ScheduledTaskCollection>
     */
    public function getRepository(): EntityRepository;
}
