<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct;

use Shopware\Core\Framework\Struct\Struct;


class ApplePayShipping extends Struct
{


    /**
     * @var array<mixed>
     */
    protected $cart;

    /**
     * @var array<mixed>
     */
    protected $shippingMethods;


    /**
     * @param array $cart
     * @param array $shippingMethods
     */
    public function __construct(array $cart, array $shippingMethods)
    {
        $this->cart = $cart;
        $this->shippingMethods = $shippingMethods;
    }


    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return 'mollie_payments_routes_apple_pay_direct_shipping_methods';
    }


    /**
     * @return array
     */
    public function getApplePayCart(): array
    {
        return $this->cart;
    }

    /**
     * @return array
     */
    public function getShippingMethods(): array
    {
        return $this->shippingMethods;
    }

}
