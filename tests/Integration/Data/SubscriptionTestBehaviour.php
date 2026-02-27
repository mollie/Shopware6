<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

trait SubscriptionTestBehaviour
{
    use IntegrationTestBehaviour;

    public function getSubscriptionByOrderId(string $orderId, Context $context): SubscriptionEntity
    {
        /** @var EntityRepository<SubscriptionCollection<SubscriptionEntity>> $repository */
        $repository = $this->getContainer()->get('mollie_subscription.repository');

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        return $repository->search($criteria, $context)->first();
    }
}
