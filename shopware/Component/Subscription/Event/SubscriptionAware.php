<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;

interface SubscriptionAware
{
    public const STORAGE_KEY_SUBSCRIPTION = 'mollieSubscription';

    public function getSubscription(): SubscriptionEntity;

    public function getSubscriptionId(): string;
}
