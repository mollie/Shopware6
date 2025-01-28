<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;

class CartException extends \Exception
{

    public static function orderNotFound(string $orderId): OrderNotFoundException
    {
        return new OrderNotFoundException($orderId);
    }

}