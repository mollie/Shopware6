<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\PriceDrift;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Mollie\Shopware\Component\Subscription\PriceDrift\SubscriptionPriceCheckFlagger;
use Mollie\Shopware\Unit\Fake\FakeConnection;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionPriceCheckFlagger::class)]
final class SubscriptionPriceCheckFlaggerTest extends TestCase
{
    private const SUBSCRIPTION_ID = '0199c1f2a3b44c5d8e9f0a1b2c3d4e5f';
    private const PRODUCT_ID = 'aaaaaaaaaaaa4aaaaaaaaaaaaaaaaaaa';
    private const SHIPPING_METHOD_ID = 'bbbbbbbbbbbb4bbbbbbbbbbbbbbbbbbb';
    private const SALES_CHANNEL_ID = 'cccccccccccc4ccccccccccccccccccc';

    public function testNoQueryRunsWithoutAnyProductId(): void
    {
        $connection = new FakeConnection();

        $flagged = $this->makeFlagger($connection)->flagByProductIds([]);

        $this->assertSame(0, $flagged);
        $this->assertSame([], $connection->getFetchedStatements());
    }

    public function testInvalidProductIdsAreDroppedBeforeTheyReachTheQuery(): void
    {
        $connection = new FakeConnection();

        $flagged = $this->makeFlagger($connection)->flagByProductIds(['not-a-uuid', '']);

        $this->assertSame(0, $flagged);
        $this->assertSame([], $connection->getFetchedStatements());
    }

    public function testProductIdsAreInlinedAsBinaryLiterals(): void
    {
        $connection = new FakeConnection();

        $this->makeFlagger($connection)->flagByProductIds([self::PRODUCT_ID, self::PRODUCT_ID]);

        $select = $connection->getFetchedStatements()[0];

        $this->assertStringContainsString("X'" . self::PRODUCT_ID . "'", $select);
        $this->assertSame(1, substr_count($select, "X'" . self::PRODUCT_ID . "'"));
        $this->assertStringContainsString('`li`.`product_id` IN', $select);
    }

    public function testShippingMethodIdsAreMatchedAgainstTheOrderDelivery(): void
    {
        $connection = new FakeConnection();

        $this->makeFlagger($connection)->flagByShippingMethodIds([self::SHIPPING_METHOD_ID]);

        $select = $connection->getFetchedStatements()[0];

        $this->assertStringContainsString('`d`.`shipping_method_id` IN', $select);
        $this->assertStringContainsString("X'" . self::SHIPPING_METHOD_ID . "'", $select);
    }

    public function testNothingIsFlaggedWhenNoSubscriptionMatches(): void
    {
        $connection = new FakeConnection();

        $flagged = $this->makeFlagger($connection)->flagByProductIds([self::PRODUCT_ID]);

        $this->assertSame(0, $flagged);
        $this->assertSame([], $connection->getExecutedStatements());
    }

    public function testMatchingSubscriptionIsFlaggedAsDirty(): void
    {
        $connection = new FakeConnection();
        $connection->withRows([['id' => self::SUBSCRIPTION_ID, 'salesChannelId' => self::SALES_CHANNEL_ID]]);
        $connection->withAffectedRows(1);

        $flagged = $this->makeFlagger($connection, $this->autoPriceUpdateSettings())->flagByProductIds([self::PRODUCT_ID]);

        $update = $connection->getExecutedStatements()[0];

        $this->assertSame(1, $flagged);
        $this->assertStringContainsString("SET `price_update_state` = '" . PriceDriftDetector::STATE_DIRTY . "'", $update);
        $this->assertStringContainsString("`price_update_state` = '" . PriceDriftDetector::STATE_NONE . "'", $update);
        $this->assertStringContainsString("X'" . self::SUBSCRIPTION_ID . "'", $update);
    }

    public function testSubscriptionIsNotFlaggedWhilePricesAreKept(): void
    {
        $connection = new FakeConnection();
        $connection->withRows([['id' => self::SUBSCRIPTION_ID, 'salesChannelId' => self::SALES_CHANNEL_ID]]);
        $settings = new SubscriptionSettings(enabled: true, priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_KEEP);

        $flagged = $this->makeFlagger($connection, $settings)->flagByProductIds([self::PRODUCT_ID]);

        $this->assertSame(0, $flagged);
        $this->assertSame([], $connection->getExecutedStatements());
    }

    public function testSubscriptionIsNotFlaggedWhileSubscriptionsAreDisabled(): void
    {
        $connection = new FakeConnection();
        $connection->withRows([['id' => self::SUBSCRIPTION_ID, 'salesChannelId' => self::SALES_CHANNEL_ID]]);
        $settings = new SubscriptionSettings(enabled: false, priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_AUTO);

        $flagged = $this->makeFlagger($connection, $settings)->flagByProductIds([self::PRODUCT_ID]);

        $this->assertSame(0, $flagged);
        $this->assertSame([], $connection->getExecutedStatements());
    }

    public function testADatabaseFailureDoesNotBreakTheProductSave(): void
    {
        $connection = new FakeConnection();
        $connection->withFailure(new \RuntimeException('connection lost'));

        $flagged = $this->makeFlagger($connection)->flagByProductIds([self::PRODUCT_ID]);

        $this->assertSame(0, $flagged);
    }

    private function makeFlagger(FakeConnection $connection, ?SubscriptionSettings $settings = null): SubscriptionPriceCheckFlagger
    {
        return new SubscriptionPriceCheckFlagger(
            $connection,
            new FakeSettingsService(subscriptionSettings: $settings ?? new SubscriptionSettings()),
            new FakeLogger()
        );
    }

    private function autoPriceUpdateSettings(): SubscriptionSettings
    {
        return new SubscriptionSettings(enabled: true, priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_AUTO);
    }
}
