<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\OrderStateSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

#[CoversClass(OrderStateSettings::class)]
final class OrderStateSettingsTest extends TestCase
{
    public function testDefaultState(): void
    {
        $settings = new OrderStateSettings();

        $this->assertNull($settings->getFinalOrderState());
        $this->assertNull($settings->getStatus(OrderTransactionStates::STATE_PAID));
    }

    public function testGetStatusReturnsMappedValue(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([
            OrderStateSettings::KEY_STATE_PAID => 'completed',
            OrderStateSettings::KEY_STATE_FAILED => 'open',
        ]);

        $this->assertSame('completed', $settings->getStatus(OrderTransactionStates::STATE_PAID));
        $this->assertSame('open', $settings->getStatus(OrderTransactionStates::STATE_FAILED));
    }

    public function testGetStatusReturnsNullForSkipValue(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([
            OrderStateSettings::KEY_STATE_PAID => OrderStateSettings::SKIP_STATE,
        ]);

        $this->assertNull($settings->getStatus(OrderTransactionStates::STATE_PAID));
    }

    public function testGetStatusReturnsNullForUnknownStatus(): void
    {
        $settings = new OrderStateSettings();

        $this->assertNull($settings->getStatus('unknown_status'));
    }

    public function testCreateFromShopwareArrayWithFinalState(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([
            OrderStateSettings::KEY_STATE_FINAL => 'completed',
        ]);

        $this->assertSame('completed', $settings->getFinalOrderState());
    }

    public function testCreateFromShopwareArrayWithDefaultsFallbackToSkip(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([]);

        $this->assertNull($settings->getStatus(OrderTransactionStates::STATE_PAID));
        $this->assertNull($settings->getFinalOrderState());
    }
}
