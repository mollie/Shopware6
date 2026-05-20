<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

final class PaymentMethodBuilder
{
    private string $id = 'payment-method-id';
    private string $handlerIdentifier = '';
    private string $name = 'Test Payment Method';

    public static function create(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withHandlerIdentifier(string $handlerIdentifier): self
    {
        $this->handlerIdentifier = $handlerIdentifier;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function build(): PaymentMethodEntity
    {
        $method = new PaymentMethodEntity();
        $method->setId($this->id);
        $method->setHandlerIdentifier($this->handlerIdentifier);
        $method->setName($this->name);

        return $method;
    }
}
