<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class PaymentSettings extends Struct
{
    public const KEY_ORDER_NUMBER_FORMAT = 'formatOrderNumber';
    public const KEY_DUE_DATE_DAYS = 'paymentMethodBankTransferDueDateDays';
    public const KEY_SHOPWARE_FAILED_PAYMENT = 'shopwareFailedPayment';
    private const MIN_DUE_DAYS = 1;
    private const MAX_DUE_DAYS = 100;

    private const KEY_ONE_CLICK_PAYMENT = 'oneClickPaymentsEnabled';
    private const KEY_ONE_CLICK_COMPACT_VIEW = 'oneClickPaymentsCompactView';

    public function __construct(private string $orderNumberFormat, private int $dueDateDays, private bool $oneClickPayment = false, private bool $oneClickCompactView = false,private bool $shopwareFailedPayment = false)
    {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $orderNumberFormat = $settings[self::KEY_ORDER_NUMBER_FORMAT] ?? '';
        $dueDateDays = $settings[self::KEY_DUE_DATE_DAYS] ?? 0;
        $oneClickPayment = $settings[self::KEY_ONE_CLICK_PAYMENT] ?? false;
        $oneClickCompactView = $settings[self::KEY_ONE_CLICK_COMPACT_VIEW] ?? false;
        $shopwareFailedPayment = $settings[self::KEY_SHOPWARE_FAILED_PAYMENT] ?? false;

        return new self($orderNumberFormat, $dueDateDays,$oneClickPayment,$oneClickCompactView,$shopwareFailedPayment);
    }

    public function getOrderNumberFormat(): string
    {
        return $this->orderNumberFormat;
    }

    public function isShopwareFailedPayment(): bool
    {
        return $this->shopwareFailedPayment;
    }

    public function getDueDateDays(): int
    {
        if ($this->dueDateDays === 0) {
            return $this->dueDateDays;
        }

        return max(min($this->dueDateDays, self::MAX_DUE_DAYS), self::MIN_DUE_DAYS);
    }

    public function isOneClickPayment(): bool
    {
        return $this->oneClickPayment;
    }

    public function isOneClickCompactView(): bool
    {
        return $this->oneClickCompactView;
    }
}
