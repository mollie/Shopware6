<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use PHPUnit\Framework\TestCase;

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
}
