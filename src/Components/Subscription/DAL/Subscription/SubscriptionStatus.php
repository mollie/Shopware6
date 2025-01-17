<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;

class SubscriptionStatus
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';

    public const PAUSED = 'paused';
    public const RESUMED = 'resumed';
    public const SKIPPED = 'skipped';


    /**
     * @param string $status
     * @return string
     */
    public static function fromMollieStatus(string $status)
    {
        switch ($status) {
            case 'pending':
                return self::PENDING;

            case 'active':
                return self::ACTIVE;

            case 'suspended':
                return self::SUSPENDED;

            case 'completed':
                return self::COMPLETED;

            case 'canceled':
                return self::CANCELED;
        }

        return self::PENDING;
    }
}
