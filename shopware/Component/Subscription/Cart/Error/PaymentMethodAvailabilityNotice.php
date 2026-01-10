<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class PaymentMethodAvailabilityNotice extends Error
{
    private const KEY = 'mollie-payments-cart-error-paymentmethod-availability';

    public function __construct(private string $lineItemId)
    {
        parent::__construct();
    }

    public function getId(): string
    {
        return $this->lineItemId;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return ['lineItemId' => $this->lineItemId];
    }
}
