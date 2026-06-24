<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressId;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressSyncer;
use Mollie\Shopware\Unit\Fake\FakeCustomerAddressRepository;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionAddressBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
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
        $this->assertNotSame($result->getBillingAddressId(), $result->getShippingAddressId());
        $this->assertSame($result->getBillingAddressId(), $repository->getUpserts()[0]['id']);
        $this->assertSame($result->getShippingAddressId(), $repository->getUpserts()[1]['id']);
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
        $this->assertSame($billingId, $result->getBillingAddressId());
        $this->assertSame($shippingId, $result->getShippingAddressId());
    }

    public function testSyncReusesBillingIdForShippingWhenAddressesAreIdentical(): void
    {
        $billing = $this->buildAddress(['firstName' => 'John', 'street' => 'Same Street 1']);
        $shipping = $this->buildAddress(['firstName' => 'John', 'street' => 'Same Street 1']);

        $repository = new FakeCustomerAddressRepository();
        $syncer = new SubscriptionAddressSyncer($repository);

        $result = $syncer->syncFromSubscription($this->buildSubscription($billing, $shipping), Context::createDefaultContext());

        $this->assertSame(1, $repository->getUpsertCount());
        $this->assertSame($result->getBillingAddressId(), $result->getShippingAddressId());
    }

    public function testSyncThrowsWhenBillingAddressMissing(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withCustomerId(self::CUSTOMER_ID)
            ->withoutBillingAddress()
            ->withShippingAddress($this->buildAddress(['firstName' => 'Jane']))
            ->build()
        ;

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
            ->build()
        ;

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
            ->build()
        ;
    }

    /**
     * @param array{firstName?:string,street?:string} $overrides
     */
    private function buildAddress(array $overrides = []): SubscriptionAddressEntity
    {
        $firstName = $overrides['firstName'] ?? 'John';

        return SubscriptionAddressBuilder::create()
            ->withId('subscription-address-' . $firstName)
            ->withSubscriptionId('subscription-id')
            ->withFirstName($firstName)
            ->withLastName($overrides['lastName'] ?? 'Doe')
            ->withStreet($overrides['street'] ?? 'Default Street 1')
            ->build()
        ;
    }
}
