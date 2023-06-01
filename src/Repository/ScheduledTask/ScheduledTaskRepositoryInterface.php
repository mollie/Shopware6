<?php

namespace Kiener\MolliePayments\Repository\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\CustomEntity\Xml\Entity;

interface ScheduledTaskRepositoryInterface
{
    /**
     * @return EntityRepository
     */
    public function getRepository(): EntityRepository;
}
