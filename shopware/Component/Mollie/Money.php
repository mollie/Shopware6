<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

final class Money implements \JsonSerializable
{
    private const MOLLIE_PRICE_PRECISION = 2;

    /**
     * ISO 4217 currencies without minor units. Mollie expects these amounts
     * without decimals (e.g. "5000" for JPY); sending "5000.00" results in an API error.
     * Extend this list if further zero-decimal currencies need to be supported.
     */
    private const ZERO_DECIMAL_CURRENCIES = ['JPY'];

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
        $decimals = $this->getDecimals();

        return [
            'value' => number_format(round($this->value, $decimals), $decimals, '.', ''),
            'currency' => $this->currency,
        ];
    }

    public function getDecimals(): int
    {
        if (in_array(strtoupper($this->currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return 0;
        }

        return self::MOLLIE_PRICE_PRECISION;
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
