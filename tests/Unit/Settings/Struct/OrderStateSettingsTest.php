<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Struct;

use Mollie\Shopware\Component\Settings\Struct\OrderStateSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

#[CoversClass(OrderStateSettings::class)]
final class OrderStateSettingsTest extends TestCase
{
    #[DataProvider('configuredPaymentStates')]
    public function testEachPaymentStatusMapsToTheConfiguredOrderState(string $configKey, string $paymentStatus): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([$configKey => 'process']);

        self::assertSame('process', $settings->getStatus($paymentStatus));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function configuredPaymentStates(): array
    {
        return [
            'paid' => [OrderStateSettings::KEY_STATE_PAID, OrderTransactionStates::STATE_PAID],
            'failed' => [OrderStateSettings::KEY_STATE_FAILED, OrderTransactionStates::STATE_FAILED],
            'cancelled' => [OrderStateSettings::KEY_STATE_CANCELLED, OrderTransactionStates::STATE_CANCELLED],
            'authorized' => [OrderStateSettings::KEY_STATE_AUTHORIZED, OrderTransactionStates::STATE_AUTHORIZED],
            'chargeback' => [OrderStateSettings::KEY_STATE_CHARGE_BACK, OrderTransactionStates::STATE_CHARGEBACK],
            'refunded' => [OrderStateSettings::KEY_STATE_REFUND, OrderTransactionStates::STATE_REFUNDED],
            'partially refunded' => [OrderStateSettings::KEY_STATE_PARTIAL_REFUND, OrderTransactionStates::STATE_PARTIALLY_REFUNDED],
        ];
    }

    public function testAnUnconfiguredPaymentStatusLeavesTheOrderStateAlone(): void
    {
        // "skip" is what the merchant selects to keep the order state untouched, so the caller must
        // not receive a target state for it.
        $settings = OrderStateSettings::createFromShopwareArray([]);

        self::assertNull($settings->getStatus(OrderTransactionStates::STATE_PAID));
    }

    public function testAPaymentStatusThatIsNotMappedAtAllLeavesTheOrderStateAlone(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([OrderStateSettings::KEY_STATE_PAID => 'process']);

        self::assertNull($settings->getStatus(OrderTransactionStates::STATE_REMINDED));
    }

    public function testTheFinalOrderStateIsReadFromTheConfiguration(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([OrderStateSettings::KEY_STATE_FINAL => 'completed']);

        self::assertSame('completed', $settings->getFinalOrderState());
    }

    public function testWithoutAConfiguredFinalOrderStateThereIsNone(): void
    {
        $settings = OrderStateSettings::createFromShopwareArray([]);

        self::assertNull($settings->getFinalOrderState());
    }

    public function testAFreshlyBuiltSettingsObjectMapsNothing(): void
    {
        $settings = new OrderStateSettings();

        self::assertNull($settings->getStatus(OrderTransactionStates::STATE_PAID));
        self::assertNull($settings->getFinalOrderState());
    }
}
