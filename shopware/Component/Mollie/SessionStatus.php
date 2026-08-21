<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * @see https://docs.mollie.com/reference/get-session
 */
enum SessionStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }
}
