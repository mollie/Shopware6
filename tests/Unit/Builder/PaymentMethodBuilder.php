<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Mollie\Shopware\Component\Mollie\PaymentMethod as MolliePaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

final class PaymentMethodBuilder
{
    private string $id = 'payment-method-id';
    private string $handlerIdentifier = '';
    private string $name = 'Test Payment Method';

    private ?MolliePaymentMethod $mollieMethod = null;

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

    /**
     * Marks the method as a Mollie one, the way the plugin's payment method extension does.
     */
    public function withMollieMethod(MolliePaymentMethod $mollieMethod): self
    {
        $this->mollieMethod = $mollieMethod;

        return $this;
    }

    public function build(): PaymentMethodEntity
    {
        $method = new PaymentMethodEntity();
        $method->setId($this->id);
        $method->setHandlerIdentifier($this->handlerIdentifier);
        $method->setName($this->name);

        if ($this->mollieMethod !== null) {
            $method->addExtension(Mollie::EXTENSION, new PaymentMethodExtension($this->id, $this->mollieMethod));
        }

        return $method;
    }
}
