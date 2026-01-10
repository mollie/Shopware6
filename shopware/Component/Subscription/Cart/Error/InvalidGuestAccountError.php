<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class InvalidGuestAccountError extends Error
{
    private const KEY = 'mollie-payments-cart-guest-account';

    public function getId(): string
    {
        return self::KEY;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return [];
    }
}
