<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Whether Mollie collects the money right at the checkout ("direct payment") or only holds it until
 * the merchant marks the order as shipped.
 *
 * Every method that supports both capture modes has its own switch in config.xml, so the merchant
 * overrides a single method for a sales channel while the rest keeps following the global choice -
 * the inheritance Shopware gives every other setting.
 */
final class CaptureSettings extends Struct
{
    use JsonSerializableTrait;
    public const KEY_PREFIX_DIRECT_PAYMENT = 'directPayment';

    /**
     * @param array<string, bool> $directPaymentMethods configuration key to whether Mollie collects right at the checkout
     */
    public function __construct(private readonly array $directPaymentMethods = [])
    {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $directPaymentMethods = [];
        foreach ($settings as $configKey => $isDirectPayment) {
            if (! str_starts_with($configKey, self::KEY_PREFIX_DIRECT_PAYMENT)) {
                continue;
            }

            // Every switch is a bool field in config.xml. Anything else under the prefix is not one
            // of them, so casting it would only invent a choice the merchant never made.
            if (! is_bool($isDirectPayment)) {
                continue;
            }

            $directPaymentMethods[$configKey] = $isDirectPayment;
        }

        return new self($directPaymentMethods);
    }

    /**
     * False for a method without a switch in config.xml, and for one whose default value Shopware
     * has not written yet. Ask hasDirectPaymentChoice() first to tell those apart from a hold the
     * merchant chose.
     */
    public function isDirectPaymentEnabled(PaymentMethod $paymentMethod): bool
    {
        return $this->directPaymentMethods[self::buildConfigKey($paymentMethod)] ?? false;
    }

    public function hasDirectPaymentChoice(PaymentMethod $paymentMethod): bool
    {
        return isset($this->directPaymentMethods[self::buildConfigKey($paymentMethod)]);
    }

    private static function buildConfigKey(PaymentMethod $paymentMethod): string
    {
        return self::KEY_PREFIX_DIRECT_PAYMENT . ucfirst($paymentMethod->value);
    }
}
