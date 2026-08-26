<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentSettings::class)]
final class PaymentSettingsTest extends TestCase
{
    public function testCanCreateApiSettingsFromArray(): void
    {
        $data = [
            PaymentSettings::KEY_ORDER_NUMBER_FORMAT => 'test-{orderNumber}'
        ];
        $settings = PaymentSettings::createFromShopwareArray($data);

        $this->assertSame('test-{orderNumber}', $settings->getOrderNumberFormat());
    }

    public function testRoundingDiffDefaults(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isFixRoundingDiffEnabled());
        $this->assertSame('', $settings->getFixRoundingDiffName());
        $this->assertSame('', $settings->getFixRoundingDiffSku());
    }

    public function testAutomaticStatusUpdateIsDisabledByDefault(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isAutomaticStatusUpdate());
    }

    public function testAutomaticStatusUpdateFromArray(): void
    {
        $data = [
            PaymentSettings::KEY_AUTOMATIC_STATUS_UPDATE => true,
        ];
        $settings = PaymentSettings::createFromShopwareArray($data);

        $this->assertTrue($settings->isAutomaticStatusUpdate());
    }

    public function testRoundingDiffFromArray(): void
    {
        $data = [
            PaymentSettings::KEY_FIX_ROUNDING_DIFF_ENABLED => true,
            PaymentSettings::KEY_FIX_ROUNDING_DIFF_NAME => 'Rounding',
            PaymentSettings::KEY_FIX_ROUNDING_DIFF_SKU => 'ROUND-1',
        ];
        $settings = PaymentSettings::createFromShopwareArray($data);

        $this->assertTrue($settings->isFixRoundingDiffEnabled());
        $this->assertSame('Rounding', $settings->getFixRoundingDiffName());
        $this->assertSame('ROUND-1', $settings->getFixRoundingDiffSku());
    }

    /**
     * The keys are the ones from config.xml. A renamed key silently turns the merchant's setting
     * off, so every one of them is pinned here.
     */
    public function testEverySettingIsReadFromItsConfigurationKey(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([
            'oneClickPaymentsEnabled' => true,
            'oneClickPaymentsCompactView' => true,
            'shopwareFailedPayment' => true,
            'createCustomersAtMollie' => true,
            'useMolliePaymentMethodLimits' => true,
            'automaticShipping' => true,
            'automaticCancellation' => true,
            'paymentLinkAllowMethodSelection' => true,
        ]);

        $this->assertTrue($settings->isOneClickPayment());
        $this->assertTrue($settings->isOneClickCompactView());
        $this->assertTrue($settings->isShopwareFailedPayment());
        $this->assertTrue($settings->forceCustomerCreation());
        $this->assertTrue($settings->useMollieLimits());
        $this->assertTrue($settings->isAutomaticShipment());
        $this->assertTrue($settings->isAutomaticCancellation());
        $this->assertTrue($settings->isPaymentLinkMethodSelectionAllowed());
    }

    public function testAnUnconfiguredShopHasEverythingSwitchedOff(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isOneClickPayment());
        $this->assertFalse($settings->isOneClickCompactView());
        $this->assertFalse($settings->isShopwareFailedPayment());
        $this->assertFalse($settings->forceCustomerCreation());
        $this->assertFalse($settings->useMollieLimits());
        $this->assertFalse($settings->isAutomaticShipment());
        $this->assertFalse($settings->isAutomaticCancellation());
        $this->assertFalse($settings->isPaymentLinkMethodSelectionAllowed());
    }

    /**
     * Mollie rejects a bank transfer due date outside 1..100 days, so a merchant typo is clamped
     * instead of failing the payment.
     */
    public function testADueDateBeyondWhatMollieAcceptsIsClamped(): void
    {
        $tooHigh = PaymentSettings::createFromShopwareArray([PaymentSettings::KEY_DUE_DATE_DAYS => 365]);
        $tooLow = PaymentSettings::createFromShopwareArray([PaymentSettings::KEY_DUE_DATE_DAYS => -5]);

        $this->assertSame(100, $tooHigh->getDueDateDays());
        $this->assertSame(1, $tooLow->getDueDateDays());
    }

    /**
     * Zero is not a typo but "no due date", so it must survive the clamping.
     */
    public function testNoDueDateStaysNoDueDate(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([]);

        $this->assertSame(0, $settings->getDueDateDays());
    }

    public function testADueDateWithinTheAllowedRangeIsKept(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([PaymentSettings::KEY_DUE_DATE_DAYS => 14]);

        $this->assertSame(14, $settings->getDueDateDays());
    }
}
