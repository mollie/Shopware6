<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

final class SubscriptionNotFoundException extends \Exception
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct(sprintf('Subscription with id %s was not found', $subscriptionId));
    }
}
