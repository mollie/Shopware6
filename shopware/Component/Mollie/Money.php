<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Money implements \JsonSerializable
{
    use JsonSerializableTrait;
    private const MOLLIE_PRICE_PRECISION = 2;
    private string $currency;
    private string $value;

    public function __construct(float $value, string $currency)
    {
        $this->currency = $currency;
        $this->value = $this->formatValue($value);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public static function fromOrder(OrderEntity $order): self
    {
        $value = $order->getAmountTotal();
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $value = $order->getAmountNet();
        }

        return new self($value, $order->getCurrency()->getIsoCode());
    }

    private function formatValue(float $value): string
    {
        return number_format(round($value, self::MOLLIE_PRICE_PRECISION), self::MOLLIE_PRICE_PRECISION, '.', '');
    }
}
