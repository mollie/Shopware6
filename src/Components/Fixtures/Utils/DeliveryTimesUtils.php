<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Utils;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class DeliveryTimesUtils
{
    /**
     * @var EntityRepository<DeliveryTimeCollection>
     */
    private $repoDeliveryTimes;

    /**
     * @param EntityRepository<DeliveryTimeCollection> $repoDeliveryTimes
     */
    public function __construct($repoDeliveryTimes)
    {
        $this->repoDeliveryTimes = $repoDeliveryTimes;
    }

    public function getRandomDeliveryTime(): DeliveryTimeEntity
    {
        $criteria = new Criteria();

        $deliveryTime = $this->repoDeliveryTimes
            ->search($criteria, Context::createDefaultContext())
            ->first()
        ;

        if (! $deliveryTime instanceof DeliveryTimeEntity) {
            throw new \RuntimeException('No delivery times found in the system. Please create at least one delivery time before installing the Mollie Payments fixtures.');
        }

        return $deliveryTime;
    }
}
