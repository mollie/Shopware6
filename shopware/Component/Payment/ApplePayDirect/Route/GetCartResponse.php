<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayCart;
use Shopware\Core\Checkout\Cart\Cart as ShopwareCart;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class GetCartResponse extends StoreApiResponse
{
    public function __construct(private ApplePayCart $cart, private ShopwareCart $shopwareCart)
    {
        $response = new ArrayStruct([
            'cart' => $this->cart,
        ],'mollie_payments_applepay_direct_cart');
        parent::__construct($response);
    }

    public function getCart(): ApplePayCart
    {
        return $this->cart;
    }

    public function getShopwareCart(): ShopwareCart
    {
        return $this->shopwareCart;
    }
}
