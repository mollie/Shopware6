<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum SubscriptionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    case PAUSED = 'paused';
    case RESUMED = 'resumed';
    case SKIPPED = 'skipped';
}
