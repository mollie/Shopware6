<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressId;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressSyncer;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeCustomerAddressRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(SubscriptionAddressSyncer::class)]
final class SubscriptionAddressSyncerTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    public function testSyncCreatesNewAddressesWhenAddressesDifferAndNotPersisted(): void
    {
        $billing = $this->buildAddress(['firstName' => 'John', 'street' => 'Billing Street 1']);
        $shipping = $this->buildAddress(['firstName' => 'John', 'street' => 'Shipping Street 2']);

        $subscription = $this->buildSubscription($billing, $shipping);
        $repository = new FakeCustomerAddressRepository();

        $syncer = new SubscriptionAddressSyncer($repository);

        $result = $syncer->syncFromSubscription($subscription, Context::createDefaultContext());

        $this->assertSame(2, $repository->getUpsertCount());
        $this->assertNotSame($result['billingAddressId'], $result['shippingAddressId']);
        $this->assertSame($result['billingAddressId'], $repository->getUpserts()[0]['id']);
        $this->assertSame($result['shippingAddressId'], $repository->getUpserts()[1]['id']);
    }

    public function testSyncReusesExistingAddressIdsWhenAlreadyPersisted(): void
    {
        $billing = $this->buildAddress(['firstName' => 'John', 'street' => 'Billing Street 1']);
        $shipping = $this->buildAddress(['firstName' => 'John', 'street' => 'Shipping Street 2']);

        $billingId = (string) new SubscriptionAddressId(self::CUSTOMER_ID, $billing);
        $shippingId = (string) new SubscriptionAddressId(self::CUSTOMER_ID, $shipping);

        $repository = new FakeCustomerAddressRepository();
        $repository->registerExistingId($billingId);
        $repository->registerExistingId($shippingId);

        $syncer = new SubscriptionAddressSyncer($repository);

        $result = $syncer->syncFromSubscription($this->buildSubscription($billing, $shipping), Context::createDefaultContext());

        $this->assertSame(0, $repository->getUpsertCount());
        $this->assertSame($billingId, $result['billingAddressId']);
        $this->assertSame($shippingId, $result['shippingAddressId']);
    }

    public function testSyncReusesBillingIdForShippingWhenAddressesAreIdentical(): void
    {
        $billing = $this->buildAddress(['firstName' => 'John', 'street' => 'Same Street 1']);
        $shipping = $this->buildAddress(['firstName' => 'John', 'street' => 'Same Street 1']);

        $repository = new FakeCustomerAddressRepository();
        $syncer = new SubscriptionAddressSyncer($repository);

        $result = $syncer->syncFromSubscription($this->buildSubscription($billing, $shipping), Context::createDefaultContext());

        $this->assertSame(1, $repository->getUpsertCount());
        $this->assertSame($result['billingAddressId'], $result['shippingAddressId']);
    }

    public function testSyncThrowsWhenBillingAddressMissing(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withCustomerId(self::CUSTOMER_ID)
            ->withoutBillingAddress()
            ->withShippingAddress($this->buildAddress(['firstName' => 'Jane']))
            ->build();

        $syncer = new SubscriptionAddressSyncer(new FakeCustomerAddressRepository());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no billing address');

        $syncer->syncFromSubscription($subscription, Context::createDefaultContext());
    }

    public function testSyncThrowsWhenShippingAddressMissing(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withCustomerId(self::CUSTOMER_ID)
            ->withBillingAddress($this->buildAddress(['firstName' => 'John']))
            ->withoutShippingAddress()
            ->build();

        $syncer = new SubscriptionAddressSyncer(new FakeCustomerAddressRepository());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no shipping address');

        $syncer->syncFromSubscription($subscription, Context::createDefaultContext());
    }

    private function buildSubscription(SubscriptionAddressEntity $billing, SubscriptionAddressEntity $shipping): SubscriptionEntity
    {
        return SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withCustomerId(self::CUSTOMER_ID)
            ->withBillingAddress($billing)
            ->withShippingAddress($shipping)
            ->build();
    }

    /**
     * @param array<string,?string> $overrides
     */
    private function buildAddress(array $overrides = []): SubscriptionAddressEntity
    {
        $address = new SubscriptionAddressEntity();
        $address->setId('subscription-address-' . ($overrides['firstName'] ?? 'default'));
        $address->setSubscriptionId('subscription-id');
        $address->setSalutationId($overrides['salutationId'] ?? 'salutation-id');
        $address->setFirstName($overrides['firstName'] ?? 'John');
        $address->setLastName($overrides['lastName'] ?? 'Doe');
        $address->setCompany($overrides['company'] ?? null);
        $address->setDepartment($overrides['department'] ?? null);
        $address->setStreet($overrides['street'] ?? 'Default Street 1');
        $address->setZipcode($overrides['zipcode'] ?? '12345');
        $address->setCity($overrides['city'] ?? 'Berlin');
        $address->setCountryId($overrides['countryId'] ?? 'country-id');
        $address->setCountryStateId($overrides['countryStateId'] ?? null);
        $address->setPhoneNumber($overrides['phoneNumber'] ?? null);
        $address->setAdditionalAddressLine1($overrides['additionalAddressLine1'] ?? null);
        $address->setAdditionalAddressLine2($overrides['additionalAddressLine2'] ?? null);

        return $address;
    }

}
