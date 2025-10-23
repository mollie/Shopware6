<?php
declare(strict_types=1);

namespace Mollie\shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class PaymentSettings extends Struct
{
    public const KEY_ORDER_NUMBER_FORMAT = 'formatOrderNumber';
    private string $orderNumberFormat = '';

    /**
     * @param string $orderNumberFormat
     */
    public function __construct(string $orderNumberFormat)
    {
        $this->orderNumberFormat = $orderNumberFormat;
    }

    public static function createFromShopwareArray(array $settings): self
    {
        $orderNumberFormat = $settings[self::KEY_ORDER_NUMBER_FORMAT] ?? '';

        return new self($orderNumberFormat);
    }

    public function getOrderNumberFormat(): string
    {
        return $this->orderNumberFormat;
    }
    
}