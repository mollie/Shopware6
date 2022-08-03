<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct;

interface MollieStatus
{
    public const ACTIVE = 'active';
    public const PENDING = 'pending';
    public const SUSPENDED = 'suspended';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';
}
