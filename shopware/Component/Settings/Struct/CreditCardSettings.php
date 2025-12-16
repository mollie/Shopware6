<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

final class CreditCardSettings
{
    private const KEY_CREDIT_CARD_COMPONENTS = 'enableCreditCardComponents';

    public function __construct(private bool $creditCardComponentsEnabled = false)
    {
    }

    /**
     * @param array<mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $creditCardComponents = $settings[self::KEY_CREDIT_CARD_COMPONENTS] ?? false;

        return new self((bool) $creditCardComponents);
    }

    public function isCreditCardComponentsEnabled(): bool
    {
        return $this->creditCardComponentsEnabled;
    }
}
