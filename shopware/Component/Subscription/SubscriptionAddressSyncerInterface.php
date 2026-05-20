<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;

interface SubscriptionAddressSyncerInterface
{
    public function syncFromSubscription(SubscriptionEntity $subscription, Context $context): RenewalAddresses;
}
