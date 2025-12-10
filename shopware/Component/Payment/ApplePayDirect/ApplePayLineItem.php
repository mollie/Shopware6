<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * https://developer.apple.com/documentation/applepayontheweb/applepaylineitem
 */
final class ApplePayLineItem implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(private string $label, private ApplePayAmount $amount, private string $type = 'final')
    {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAmount(): ApplePayAmount
    {
        return $this->amount;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
