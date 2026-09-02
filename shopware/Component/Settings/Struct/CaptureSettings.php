<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Which methods hold the money until the merchant ships and which ones Mollie collects right at the
 * checkout ("direct payment").
 *
 * The configuration stores the methods the merchant switched *off*, not the ones that are on. That
 * way a method that newly supports a direct payment - it only has to carry the two capture marker
 * interfaces - is enabled without a configuration change and without a migration.
 */
final class CaptureSettings extends Struct
{
    use JsonSerializableTrait;
    public const KEY_DISABLED_METHODS = 'directPaymentDisabledMethods';

    /**
     * @param list<string> $disabledMethods Mollie names of the methods that keep the hold
     */
    public function __construct(private readonly array $disabledMethods = [])
    {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $disabledMethods = $settings[self::KEY_DISABLED_METHODS] ?? [];
        if (! is_array($disabledMethods)) {
            return new self();
        }

        return new self(array_values(array_map('strval', $disabledMethods)));
    }

    public function isDirectPaymentEnabled(PaymentMethod $paymentMethod): bool
    {
        return ! in_array($paymentMethod->value, $this->disabledMethods, true);
    }
}
