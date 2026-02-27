<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Subscription\Action\CancelAction;
use Mollie\Shopware\Component\Subscription\Action\PauseAction;
use Mollie\Shopware\Component\Subscription\Action\SkipAction;

enum SubscriptionStatus: string
{
    /** Mollie status */
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    /** Internal Status */
    case PAUSED = 'paused';

    case RESUMED = 'resumed';
    case SKIPPED = 'skipped';
    case SKIPPED_AFTER_RENEWAL = 'skipped_after_renewal';
    case PAUSED_AFTER_RENEWAL = 'paused_after_renewal';
    case CANCELED_AFTER_RENEWAL = 'canceled_after_renewal';

    public function isActive(): bool
    {
        return $this === self::ACTIVE || $this === self::RESUMED;
    }

    public function isInterrupted(): bool
    {
        return $this === self::PAUSED || $this === self::SKIPPED;
    }

    public function getAction(): ?string
    {
        return match ($this) {
            self::SKIPPED_AFTER_RENEWAL => SkipAction::getActioName(),
            self::PAUSED_AFTER_RENEWAL => PauseAction::getActioName(),
            self::CANCELED_AFTER_RENEWAL => CancelAction::getActioName(),
            default => null,
        };
    }
}
