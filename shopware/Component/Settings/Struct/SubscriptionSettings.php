<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class SubscriptionSettings extends Struct
{
    use JsonSerializableTrait;
    public const KEY_ENABLED = 'subscriptionsEnabled';
    public const KEY_SHOW_INDICATOR = 'subscriptionsShowIndicator';
    public const KEY_ALLOW_EDIT_ADDRESS = 'subscriptionsAllowAddressEditing';
    public const KEY_ALLOW_PAUSE_RESUME = 'subscriptionsAllowPauseResume';
    public const KEY_ALLOW_SKIP = 'subscriptionsAllowSkip';
    public const KEY_ALLOW_REORDER = 'subscriptionsAllowReorder';
    public const KEY_ALLOW_UPDATE_PAYMENT = 'subscriptionsAllowUpdatePayment';
    public const KEY_SKIP_IF_FAILED = 'subscriptionSkipRenewalsOnFailedPayments';
    public const KEY_REMINDER_DAYS = 'subscriptionsReminderDays';
    public const KEY_CANCEL_DAYS = 'subscriptionsCancellationDays';
    public const KEY_PRICE_UPDATE_MODE = 'subscriptionsPriceUpdateMode';
    public const KEY_PRICE_UPDATE_NOTICE_DAYS = 'subscriptionsPriceUpdateNoticeDays';

    public const PRICE_UPDATE_MODE_KEEP = 'keep';
    public const PRICE_UPDATE_MODE_AUTO = 'auto';

    public function __construct(private bool $enabled = false,
        private bool $showIndicator = false,
        private bool $allowEditAddress = false,
        private bool $allowPauseAndResume = false,
        private bool $allowSkip = false,
        private bool $allowReorder = true,
        private bool $allowUpdatePayment = true,
        private bool $skipIfFailed = false,
        private int $reminderDays = 0,
        private int $cancelDays = 0,
        private string $priceUpdateMode = self::PRICE_UPDATE_MODE_KEEP,
        private int $priceUpdateNoticeDays = 0,
    ) {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $enabled = $settings[self::KEY_ENABLED] ?? false;
        $showIndicator = $settings[self::KEY_SHOW_INDICATOR] ?? false;
        $allowEditAddress = $settings[self::KEY_ALLOW_EDIT_ADDRESS] ?? false;
        $allowPauseAndResume = $settings[self::KEY_ALLOW_PAUSE_RESUME] ?? false;
        $allowSkip = $settings[self::KEY_ALLOW_SKIP] ?? false;
        $allowReorder = $settings[self::KEY_ALLOW_REORDER] ?? true;
        $allowUpdatePayment = $settings[self::KEY_ALLOW_UPDATE_PAYMENT] ?? true;
        $skipIfFailed = $settings[self::KEY_SKIP_IF_FAILED] ?? false;
        $reminderDays = $settings[self::KEY_REMINDER_DAYS] ?? 0;
        $cancelDays = $settings[self::KEY_CANCEL_DAYS] ?? 0;
        $priceUpdateMode = $settings[self::KEY_PRICE_UPDATE_MODE] ?? self::PRICE_UPDATE_MODE_KEEP;
        $priceUpdateNoticeDays = $settings[self::KEY_PRICE_UPDATE_NOTICE_DAYS] ?? 0;

        return new self(
            (bool) $enabled,
            (bool) $showIndicator,
            (bool) $allowEditAddress,
            (bool) $allowPauseAndResume,
            (bool) $allowSkip,
            (bool) $allowReorder,
            (bool) $allowUpdatePayment,
            (bool) $skipIfFailed,
            (int) $reminderDays,
            (int) $cancelDays,
            (string) $priceUpdateMode,
            (int) $priceUpdateNoticeDays
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isShowIndicator(): bool
    {
        return $this->showIndicator;
    }

    public function isAllowEditAddress(): bool
    {
        return $this->allowEditAddress;
    }

    public function isAllowPauseAndResume(): bool
    {
        return $this->allowPauseAndResume;
    }

    public function isAllowSkip(): bool
    {
        return $this->allowSkip;
    }

    public function isAllowReorder(): bool
    {
        return $this->allowReorder;
    }

    public function isAllowUpdatePayment(): bool
    {
        return $this->allowUpdatePayment;
    }

    public function isSkipIfFailed(): bool
    {
        return $this->skipIfFailed;
    }

    public function getReminderDays(): int
    {
        return $this->reminderDays;
    }

    public function getCancelDays(): int
    {
        return $this->cancelDays;
    }

    public function getPriceUpdateMode(): string
    {
        return $this->priceUpdateMode;
    }

    public function getPriceUpdateNoticeDays(): int
    {
        return $this->priceUpdateNoticeDays;
    }

    public function isAutoPriceUpdate(): bool
    {
        return $this->priceUpdateMode === self::PRICE_UPDATE_MODE_AUTO;
    }
}
