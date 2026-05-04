<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractListSubscriptionsRoute
{
    abstract public function getDecorated(): self;

    abstract public function list(Criteria $criteria, SalesChannelContext $context): SubscriptionsListResponse;
}
