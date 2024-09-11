<?php

namespace Kiener\MolliePayments\Repository\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

interface ScheduledTaskRepositoryInterface
{
    /**
     * @return EntityRepository
     */
    public function getRepository(): EntityRepository;
}
