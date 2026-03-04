<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Framework\Context;

interface SubscriptionDataServiceInterface
{
    public function findById(string $subscriptionId, Context $context): SubscriptionDataStruct;
}
