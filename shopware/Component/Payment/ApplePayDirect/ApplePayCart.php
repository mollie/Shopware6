<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * https://developer.apple.com/documentation/applepayontheweb/applepaypaymentrequest
 */
final class ApplePayCart extends Struct implements \JsonSerializable
{
    use JsonSerializableTrait;

    /**
     * @var ApplePayLineItem[]
     */
    private array $lineItems = [];
    private ApplePayLineItem $total;

    public function __construct(private string $label,private ApplePayAmount $amount)
    {
        $this->total = new ApplePayLineItem($this->label,$this->amount);
    }

    public function addItem(ApplePayLineItem $item): void
    {
        $this->lineItems[] = $item;
    }

    public function getApiAlias(): string
    {
        return 'mollie_payments_applepay_direct_cart';
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        unset($vars['extensions']);
        $this->convertDateTimePropertiesToJsonStringRepresentation($vars);

        return $vars;
    }
}
