<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\Struct;

class AddProductStruct extends Struct
{

    /**
     * @var Cart
     */
    protected $cart;


    /**
     * @param Cart $cart
     */
    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return 'mollie_payments_routes_apple_pay_direct_add_product';
    }

    /**
     * @return Cart
     */
    public function getCart(): string
    {
        return $this->cart;
    }


}
