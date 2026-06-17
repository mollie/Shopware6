<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

final class Money implements \JsonSerializable
{
    private const MOLLIE_PRICE_PRECISION = 2;

    public function __construct(
        private float $value,
        private string $currency,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => number_format(round($this->value, self::MOLLIE_PRICE_PRECISION), self::MOLLIE_PRICE_PRECISION, '.', ''),
            'currency' => $this->currency,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return json_decode((string) json_encode($this), true);
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public static function fromOrder(OrderEntity $order, CurrencyEntity $currency): self
    {
        $value = $order->getAmountTotal();
        if ((string) $order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $value = $order->getAmountNet();
        }

        return new self($value, $currency->getIsoCode());
    }
}
