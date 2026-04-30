<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(SubscriptionEntity::class)]
final class SubscriptionEntityTest extends TestCase
{
    public function testGettersReflectSetterValuesForAllProperties(): void
    {
        $entity = new SubscriptionEntity();

        $entity->setCustomerId('customer-id');
        $entity->setMollieId('sub_test123');
        $entity->setMollieCustomerId('cst_test123');
        $entity->setStatus(SubscriptionStatus::ACTIVE->value);
        $entity->setDescription('Monthly box');
        $entity->setAmount(19.99);
        $entity->setOrderId('order-id');
        $entity->setOrderVersionId('order-version-id');
        $entity->setMandateId('mdt_test123');
        $entity->setSalesChannelId('sales-channel-id');
        $entity->setBillingAddressId('billing-address-id');
        $entity->setShippingAddressId('shipping-address-id');
        $entity->setCurrencyId('currency-id');

        $lastReminded = new \DateTime('2026-04-01');
        $nextPayment = new \DateTime('2026-05-01');
        $canceledAt = new \DateTime('2026-04-15');
        $cancelUntil = new \DateTime('2026-04-30');
        $entity->setLastRemindedAt($lastReminded);
        $entity->setNextPaymentAt($nextPayment);
        $entity->setCanceledAt($canceledAt);
        $entity->setCancelUntil($cancelUntil);

        $currency = new CurrencyEntity();
        $entity->setCurrency($currency);
        $totalRounding = new CashRoundingConfig(2, 0.01, true);
        $itemRounding = new CashRoundingConfig(2, 0.01, true);
        $entity->setTotalRounding($totalRounding);
        $entity->setItemRounding($itemRounding);

        $billingAddress = new SubscriptionAddressEntity();
        $shippingAddress = new SubscriptionAddressEntity();
        $entity->setBillingAddress($billingAddress);
        $entity->setShippingAddress($shippingAddress);

        $order = new OrderEntity();
        $entity->setOrder($order);

        $customer = new CustomerEntity();
        $entity->setCustomer($customer);

        $addresses = new SubscriptionAddressCollection();
        $entity->setAddresses($addresses);

        $historyEntries = new SubscriptionHistoryCollection();
        $entity->setHistoryEntries($historyEntries);

        $this->assertSame('customer-id', $entity->getCustomerId());
        $this->assertSame('sub_test123', $entity->getMollieId());
        $this->assertSame('cst_test123', $entity->getMollieCustomerId());
        $this->assertSame(SubscriptionStatus::ACTIVE->value, $entity->getStatus());
        $this->assertSame('Monthly box', $entity->getDescription());
        $this->assertSame(19.99, $entity->getAmount());
        $this->assertSame('order-id', $entity->getOrderId());
        $this->assertSame('order-version-id', $entity->getOrderVersionId());
        $this->assertSame('mdt_test123', $entity->getMandateId());
        $this->assertSame('sales-channel-id', $entity->getSalesChannelId());
        $this->assertSame('billing-address-id', $entity->getBillingAddressId());
        $this->assertSame('shipping-address-id', $entity->getShippingAddressId());
        $this->assertSame('currency-id', $entity->getCurrencyId());
        $this->assertSame($lastReminded, $entity->getLastRemindedAt());
        $this->assertSame($nextPayment, $entity->getNextPaymentAt());
        $this->assertSame($canceledAt, $entity->getCanceledAt());
        $this->assertSame($cancelUntil, $entity->getCancelUntil());
        $this->assertSame($currency, $entity->getCurrency());
        $this->assertSame($totalRounding, $entity->getTotalRounding());
        $this->assertSame($itemRounding, $entity->getItemRounding());
        $this->assertSame($billingAddress, $entity->getBillingAddress());
        $this->assertSame($shippingAddress, $entity->getShippingAddress());
        $this->assertSame($order, $entity->getOrder());
        $this->assertSame($customer, $entity->getCustomer());
        $this->assertSame($addresses, $entity->getAddresses());
        $this->assertSame($historyEntries, $entity->getHistoryEntries());
    }

    public function testIsConfirmedReturnsFalseWhenMollieIdIsEmpty(): void
    {
        $entity = new SubscriptionEntity();

        $this->assertFalse($entity->isConfirmed());
    }

    public function testIsConfirmedReturnsTrueWhenMollieIdIsSet(): void
    {
        $entity = new SubscriptionEntity();
        $entity->setMollieId('sub_test123');

        $this->assertTrue($entity->isConfirmed());
    }

    public function testIsActiveTreatsEmptyStatusWithoutCanceledAtAsActive(): void
    {
        $entity = new SubscriptionEntity();

        $this->assertTrue($entity->isActive());
    }

    public function testIsActiveTreatsEmptyStatusWithCanceledAtAsInactive(): void
    {
        $entity = new SubscriptionEntity();
        $entity->setCanceledAt(new \DateTime());

        $this->assertFalse($entity->isActive());
    }

    public function testGetMetadataReturnsEmptyMetadataWhenNotSet(): void
    {
        $entity = new SubscriptionEntity();
        $entity->setMetadata(new SubscriptionMetadata('2026-01-01', 1, IntervalUnit::MONTHS));

        $metadata = $entity->getMetadata();

        $this->assertSame('2026-01-01', $metadata->getStartDate());
        $this->assertSame(1, $metadata->getIntervalValue());
        $this->assertSame(IntervalUnit::MONTHS, $metadata->getIntervalUnit());
    }

    public function testGetHistoryEntriesReturnsEmptyCollectionWhenNotSet(): void
    {
        $entity = new SubscriptionEntity();

        $this->assertCount(0, $entity->getHistoryEntries());
    }

    /**
     * @param array<string,bool> $expectedFlags
     */
    #[DataProvider('statusFlagProvider')]
    public function testStatusBasedFlags(string $status, array $expectedFlags): void
    {
        $entity = new SubscriptionEntity();
        $entity->setStatus($status);

        foreach ($expectedFlags as $method => $expected) {
            $this->assertSame(
                $expected,
                $entity->{$method}(),
                sprintf('%s() with status "%s" should be %s', $method, $status, $expected ? 'true' : 'false')
            );
        }
    }

    /**
     * @return array<string,array{0:string,1:array<string,bool>}>
     */
    public static function statusFlagProvider(): array
    {
        $allFalse = [
            'isActive' => false,
            'isPaused' => false,
            'isSkipped' => false,
            'isRenewingAllowed' => false,
            'isResumeAllowed' => false,
            'isUpdatePaymentAllowed' => false,
            'isCancellationAllowed' => true,
            'isSkipAllowed' => false,
            'isPauseAllowed' => false,
        ];

        return [
            'pending' => [SubscriptionStatus::PENDING->value, [
                'isActive' => false,
                'isPaused' => false,
                'isSkipped' => false,
                'isRenewingAllowed' => false,
                'isResumeAllowed' => false,
                'isUpdatePaymentAllowed' => false,
                'isCancellationAllowed' => false,
                'isSkipAllowed' => false,
                'isPauseAllowed' => false,
            ]],
            'active' => [SubscriptionStatus::ACTIVE->value, [
                'isActive' => true,
                'isPaused' => false,
                'isSkipped' => false,
                'isRenewingAllowed' => true,
                'isResumeAllowed' => false,
                'isUpdatePaymentAllowed' => true,
                'isCancellationAllowed' => true,
                'isSkipAllowed' => true,
                'isPauseAllowed' => true,
            ]],
            'paused' => [SubscriptionStatus::PAUSED->value, [
                'isActive' => false,
                'isPaused' => true,
                'isSkipped' => false,
                'isRenewingAllowed' => false,
                'isResumeAllowed' => true,
                'isUpdatePaymentAllowed' => false,
                'isCancellationAllowed' => true,
                'isSkipAllowed' => false,
                'isPauseAllowed' => false,
            ]],
            'resumed' => [SubscriptionStatus::RESUMED->value, [
                'isActive' => true,
                'isPaused' => false,
                'isSkipped' => false,
                'isRenewingAllowed' => true,
                'isResumeAllowed' => false,
                'isUpdatePaymentAllowed' => true,
                'isCancellationAllowed' => true,
                'isSkipAllowed' => true,
                'isPauseAllowed' => true,
            ]],
            'skipped' => [SubscriptionStatus::SKIPPED->value, [
                'isActive' => false,
                'isPaused' => false,
                'isSkipped' => true,
                'isRenewingAllowed' => true,
                'isResumeAllowed' => false,
                'isUpdatePaymentAllowed' => false,
                'isCancellationAllowed' => true,
                'isSkipAllowed' => false,
                'isPauseAllowed' => false,
            ]],
            'completed' => [SubscriptionStatus::COMPLETED->value, [
                'isActive' => false,
                'isPaused' => false,
                'isSkipped' => false,
                'isRenewingAllowed' => true,
                'isResumeAllowed' => false,
                'isUpdatePaymentAllowed' => false,
                'isCancellationAllowed' => true,
                'isSkipAllowed' => false,
                'isPauseAllowed' => false,
            ]],
            'canceled' => [SubscriptionStatus::CANCELED->value, [
                'isActive' => false,
                'isPaused' => false,
                'isSkipped' => false,
                'isRenewingAllowed' => false,
                'isResumeAllowed' => true,
                'isUpdatePaymentAllowed' => false,
                'isCancellationAllowed' => false,
                'isSkipAllowed' => false,
                'isPauseAllowed' => false,
            ]],
            'suspended' => [SubscriptionStatus::SUSPENDED->value, $allFalse],
        ];
    }
}
