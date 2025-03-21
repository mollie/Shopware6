<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\CartServiceInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeCartService implements CartServiceInterface
{
    /**
     * @var Cart
     */
    private $cart;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(Cart $cart, SalesChannelContext $salesChannelContext)
    {
        $this->cart = $cart;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart
    {
        return $this->cart;
    }

    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart
    {
        return $this->cart;
    }

    public function updateCart(Cart $cart): void
    {
        // TODO: Implement updateCart() method.
    }

    public function getShippingCosts(Cart $cart): float
    {
        return 0;
    }

    public function order(Cart $cart, SalesChannelContext $salesChannelContext): string
    {
        return '';
    }

    public function updateCountry(SalesChannelContext $context, string $countryID): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function updateShippingMethod(SalesChannelContext $context, string $shippingMethodID): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext
    {
        // TODO: Implement updatePaymentMethod() method.
    }

    public function persistCart(Cart $cart, SalesChannelContext $context): Cart
    {
        // TODO: Implement persistCart() method.
    }
}
