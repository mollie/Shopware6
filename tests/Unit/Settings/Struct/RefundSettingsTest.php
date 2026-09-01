<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Struct;

use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundSettings::class)]
final class RefundSettingsTest extends TestCase
{
    public function testEveryConfigurationKeyReachesItsGetter(): void
    {
        $settings = RefundSettings::createFromShopwareArray([
            RefundSettings::KEY_ENABLED => true,
            RefundSettings::KEY_VERIFY_REFUND => true,
            RefundSettings::KEY_AUTO_STOCK_RESET => true,
            RefundSettings::KEY_SHOW_INSTRUCTIONS => true,
            RefundSettings::KEY_CREATE_CREDIT_NOTES => true,
            RefundSettings::KEY_CREDIT_NOTES_PREFIX => 'CN-',
            RefundSettings::KEY_CREDIT_NOTES_SUFFIX => '-2026',
            RefundSettings::KEY_RETURN_MANAGEMENT_DISABLED => true,
        ]);

        $this->assertTrue($settings->isEnabled());
        $this->assertTrue($settings->isVerifyRefund());
        $this->assertTrue($settings->isAutoStockReset());
        $this->assertTrue($settings->isShowInstructions());
        $this->assertTrue($settings->isCreateCreditNotes());
        $this->assertSame('CN-', $settings->getCreditNotesPrefix());
        $this->assertSame('-2026', $settings->getCreditNotesSuffix());
        $this->assertTrue($settings->isReturnManagementDisabled());
    }

    public function testRefundManagerIsOffForAShopThatNeverConfiguredIt(): void
    {
        $settings = RefundSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isEnabled());
        $this->assertFalse($settings->isVerifyRefund());
        $this->assertFalse($settings->isAutoStockReset());
        $this->assertFalse($settings->isShowInstructions());
        $this->assertFalse($settings->isCreateCreditNotes());
        $this->assertSame('', $settings->getCreditNotesPrefix());
        $this->assertSame('', $settings->getCreditNotesSuffix());
        $this->assertFalse($settings->isReturnManagementDisabled());
    }

    public function testConfigurationValuesAreCastToTheirTargetType(): void
    {
        $settings = RefundSettings::createFromShopwareArray([
            RefundSettings::KEY_ENABLED => '1',
            RefundSettings::KEY_CREDIT_NOTES_PREFIX => 42,
        ]);

        $this->assertTrue($settings->isEnabled());
        $this->assertSame('42', $settings->getCreditNotesPrefix());
    }

    public function testCreditNoteLabelIsWrappedInPrefixAndSuffix(): void
    {
        $settings = RefundSettings::createFromShopwareArray([
            RefundSettings::KEY_CREDIT_NOTES_PREFIX => 'CN-',
            RefundSettings::KEY_CREDIT_NOTES_SUFFIX => '-2026',
        ]);

        $this->assertSame('CN-1001-2026', $settings->getCreditNoteLabel('1001'));
    }

    public function testCreditNoteLabelIsTheBareLabelWithoutPrefixAndSuffix(): void
    {
        $settings = RefundSettings::createFromShopwareArray([]);

        $this->assertSame('1001', $settings->getCreditNoteLabel('1001'));
    }
}
