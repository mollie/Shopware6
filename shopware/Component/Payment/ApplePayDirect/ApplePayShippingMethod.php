<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * https://developer.apple.com/documentation/applepayontheweb/applepayshippingmethod
 */
final class ApplePayShippingMethod implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(private string $identifier, private string $label, private string $detail, private ApplePayAmount $amount)
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getAmount(): ApplePayAmount
    {
        return $this->amount;
    }
}
