<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

final class SubscriptionsDisabledException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Subscriptions are disabled for this Sales Channel');
    }
}
