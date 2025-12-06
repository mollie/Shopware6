<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class PaymentSettings extends Struct
{
    public const KEY_ORDER_NUMBER_FORMAT = 'formatOrderNumber';
    public const KEY_DUE_DATE_DAYS = 'paymentMethodBankTransferDueDateDays';
    private const MIN_DUE_DAYS = 1;
    private const MAX_DUE_DAYS = 100;

    public function __construct(private string $orderNumberFormat, private int $dueDateDays)
    {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $orderNumberFormat = $settings[self::KEY_ORDER_NUMBER_FORMAT] ?? '';
        $dueDateDays = $settings[self::KEY_DUE_DATE_DAYS] ?? 0;

        return new self($orderNumberFormat, $dueDateDays);
    }

    public function getOrderNumberFormat(): string
    {
        return $this->orderNumberFormat;
    }

    public function getDueDateDays(): int
    {
        if ($this->dueDateDays === 0) {
            return $this->dueDateDays;
        }

        return max(min($this->dueDateDays, self::MAX_DUE_DAYS), self::MIN_DUE_DAYS);
    }
}
