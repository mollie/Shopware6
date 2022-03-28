<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Cart\Error\MixedCartBlockError;

use Shopware\Core\Checkout\Cart\Error\Error;


class MixedCartBlockError extends Error
{


    private const KEY = 'mollie-payments-cart-error-mixedcart';


    /**
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return self::KEY;
    }

    /**
     * @return string
     */
    public function getMessageKey(): string
    {
        return self::KEY;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    /**
     * @return bool
     */
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
