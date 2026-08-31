<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;

final class CartBuilder
{
    private string $token = 'test-token';
    /** @var list<LineItem> */
    private array $lineItems = [];
    /** @var list<Error> */
    private array $errors = [];
    private ?CartPrice $price = null;
    private ?CalculatedPrice $shippingCosts = null;

    public static function create(): self
    {
        return new self();
    }

    public function withToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function withLineItem(LineItem $lineItem): self
    {
        $this->lineItems[] = $lineItem;

        return $this;
    }

    /**
     * @param list<LineItem> $lineItems
     */
    public function withLineItems(array $lineItems): self
    {
        $this->lineItems = $lineItems;

        return $this;
    }

    public function withPrice(CartPrice $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * The shipping costs of a cart live on its delivery, so setting them creates one. Only the
     * costs matter to the callers; positions, date and location are filled with placeholders.
     */
    public function withShippingCosts(CalculatedPrice $shippingCosts): self
    {
        $this->shippingCosts = $shippingCosts;

        return $this;
    }

    public function withError(Error $error): self
    {
        $this->errors[] = $error;

        return $this;
    }

    public function build(): Cart
    {
        $cart = new Cart($this->token);
        $cart->setLineItems(new LineItemCollection($this->lineItems));

        if ($this->price instanceof CartPrice) {
            $cart->setPrice($this->price);
        }

        if ($this->shippingCosts instanceof CalculatedPrice) {
            $cart->setDeliveries(new DeliveryCollection([$this->buildDelivery($this->shippingCosts)]));
        }

        foreach ($this->errors as $error) {
            $cart->getErrors()->add($error);
        }

        return $cart;
    }

    private function buildDelivery(CalculatedPrice $shippingCosts): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');
        $shippingMethod->setName('Standard');

        $country = new CountryEntity();
        $country->setId('country-id');
        $country->setIso('DE');

        $deliveryDate = new DeliveryDate(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-02'));

        return new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            ShippingLocation::createFromCountry($country),
            $shippingCosts
        );
    }
}
