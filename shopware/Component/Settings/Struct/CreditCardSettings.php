<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

final class CreditCardSettings
{
    private const KEY_CREDIT_CARD_COMPONENTS = 'enableCreditCardComponents';
    private const KEY_ONE_CLICK_PAYMENT = 'oneClickPaymentsEnabled';
    private const KEY_ONE_CLICK_COMPACT_VIEW = 'oneClickPaymentsCompactView';

    public function __construct(private bool $creditCardComponentsEnabled = false, private bool $oneClickPayment = false, private bool $oneClickCompactView = false)
    {
    }

    /**
     * @param array<mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $creditCardComponents = $settings[self::KEY_CREDIT_CARD_COMPONENTS] ?? false;
        $oneClickPayment = $settings[self::KEY_ONE_CLICK_PAYMENT] ?? false;
        $oneClickCompactView = $settings[self::KEY_ONE_CLICK_COMPACT_VIEW] ?? false;

        return new self((bool) $creditCardComponents, (bool) $oneClickPayment,(bool) $oneClickCompactView);
    }

    public function isCreditCardComponentsEnabled(): bool
    {
        return $this->creditCardComponentsEnabled;
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
