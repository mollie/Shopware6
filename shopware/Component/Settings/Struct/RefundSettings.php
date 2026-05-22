<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class RefundSettings extends Struct
{
    public const KEY_ENABLED = 'refundManagerEnabled';
    public const KEY_VERIFY_REFUND = 'refundManagerVerifyRefund';
    public const KEY_AUTO_STOCK_RESET = 'refundManagerAutoStockReset';
    public const KEY_SHOW_INSTRUCTIONS = 'refundManagerShowInstructions';
    public const KEY_CREATE_CREDIT_NOTES = 'refundManagerCreateCreditNotes';
    public const KEY_CREDIT_NOTES_PREFIX = 'refundManagerCreateCreditNotesPrefix';
    public const KEY_CREDIT_NOTES_SUFFIX = 'refundManagerCreateCreditNotesSuffix';

    public function __construct(
        private readonly bool $enabled = false,
        private readonly bool $verifyRefund = false,
        private readonly bool $autoStockReset = false,
        private readonly bool $showInstructions = false,
        private readonly bool $createCreditNotes = false,
        private readonly string $creditNotesPrefix = '',
        private readonly string $creditNotesSuffix = '',
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        return new self(
            (bool) ($settings[self::KEY_ENABLED] ?? false),
            (bool) ($settings[self::KEY_VERIFY_REFUND] ?? false),
            (bool) ($settings[self::KEY_AUTO_STOCK_RESET] ?? false),
            (bool) ($settings[self::KEY_SHOW_INSTRUCTIONS] ?? false),
            (bool) ($settings[self::KEY_CREATE_CREDIT_NOTES] ?? false),
            (string) ($settings[self::KEY_CREDIT_NOTES_PREFIX] ?? ''),
            (string) ($settings[self::KEY_CREDIT_NOTES_SUFFIX] ?? ''),
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isVerifyRefund(): bool
    {
        return $this->verifyRefund;
    }

    public function isAutoStockReset(): bool
    {
        return $this->autoStockReset;
    }

    public function isShowInstructions(): bool
    {
        return $this->showInstructions;
    }

    public function isCreateCreditNotes(): bool
    {
        return $this->createCreditNotes;
    }

    public function getCreditNotesPrefix(): string
    {
        return $this->creditNotesPrefix;
    }

    public function getCreditNotesSuffix(): string
    {
        return $this->creditNotesSuffix;
    }
}
