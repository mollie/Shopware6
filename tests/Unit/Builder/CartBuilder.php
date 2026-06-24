<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

final class CartBuilder
{
    private string $token = 'test-token';
    /** @var list<LineItem> */
    private array $lineItems = [];
    /** @var list<Error> */
    private array $errors = [];

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

    public function withError(Error $error): self
    {
        $this->errors[] = $error;

        return $this;
    }

    public function build(): Cart
    {
        $cart = new Cart($this->token);
        $cart->setLineItems(new LineItemCollection($this->lineItems));

        foreach ($this->errors as $error) {
            $cart->getErrors()->add($error);
        }

        return $cart;
    }
}
