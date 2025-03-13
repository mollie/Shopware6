<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Cart\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class PaymentMethodAvailabilityNotice extends Error
{
    private const KEY = 'mollie-payments-cart-error-paymentmethod-availability';

    /**
     * @var string
     */
    private $lineItemId;

    public function __construct(string $lineItemId)
    {
        $this->lineItemId = $lineItemId;

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
