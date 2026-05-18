<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ExpressMethod\AddressSynchronizer;
use Mollie\Shopware\Unit\Fake\FakeCustomerAddressSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(AddressSynchronizer::class)]
final class AddressSynchronizerTest extends TestCase
{
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    /**
     * Two distinct addresses, neither exists yet → two new rows inserted;
     * defaults mapped to the correct roles.
     */
    public function testTwoNewAddressesAreInsertedAndDefaultsAreSetCorrectly(): void
    {
        $shipping = $this->makeAddress('John', 'Doe', 'john@example.com', 'Shipping St 1', 'Berlin', '10115', 'DE');
        $billing = $this->makeAddress('John', 'Doe', 'john@example.com', 'Billing Ave 5', 'Munich', '80331', 'DE');

        $addressRepo = new FakeCustomerAddressSearchRepository([]);
        $customerRepo = new FakeCustomerRepository();

        $customer = $this->makeCustomer('customer-1', 'salutation-1');

        $synchronizer = new AddressSynchronizer($addressRepo, $customerRepo);
        $result = $synchronizer->syncAddresses($customer, $shipping, $billing, $this->makeSalesChannelContext(), ['DE' => 'country-de-uuid']);

        $payload = $customerRepo->getLastUpsert();

        $shippingId = $result->getShippingAddressId();
        $billingId = $result->getBillingAddressId();

        // The two IDs must be distinct (separate rows for distinct addresses)
        $this->assertNotSame($shippingId, $billingId);

        $this->assertSame($shippingId, $payload['defaultShippingAddressId'], 'shipping default must point to shipping address');
        $this->assertSame($billingId, $payload['defaultBillingAddressId'], 'billing default must point to billing address');

        // Both addresses inserted
        $this->assertCount(2, $payload['addresses']);
    }

    /**
     * Billing and shipping are identical (same MD5) → one row inserted;
     * both defaults point to that single row.
     */
    public function testIdenticalBillingAndShippingInsertOneRowAndShareDefaults(): void
    {
        $address = $this->makeAddress('Jane', 'Smith', 'jane@example.com', 'One Way 7', 'Hamburg', '20095', 'DE');

        $addressRepo = new FakeCustomerAddressSearchRepository([]);
        $customerRepo = new FakeCustomerRepository();

        $customer = $this->makeCustomer('customer-same');

        $synchronizer = new AddressSynchronizer($addressRepo, $customerRepo);
        $result = $synchronizer->syncAddresses($customer, $address, $address, $this->makeSalesChannelContext(), ['DE' => 'country-de-uuid']);

        $payload = $customerRepo->getLastUpsert();

        $this->assertSame(
            $result->getShippingAddressId(),
            $result->getBillingAddressId(),
            'identical billing and shipping must share one address entity'
        );
        $this->assertSame($payload['defaultShippingAddressId'], $payload['defaultBillingAddressId']);
        $this->assertCount(1, $payload['addresses'], 'only one address row should be inserted for identical addresses');
    }

    /**
     * Billing is null (Shopware "same as shipping" convention) → one row inserted;
     * both defaults point to that single row.
     */
    public function testNullBillingUsesSameIdForBothDefaults(): void
    {
        $shipping = $this->makeAddress('Jane', 'Smith', 'jane@example.com', 'One Way 7', 'Hamburg', '20095', 'DE');

        $addressRepo = new FakeCustomerAddressSearchRepository([]);
        $customerRepo = new FakeCustomerRepository();

        $customer = $this->makeCustomer('customer-2');

        $synchronizer = new AddressSynchronizer($addressRepo, $customerRepo);
        $result = $synchronizer->syncAddresses($customer, $shipping, null, $this->makeSalesChannelContext(), ['DE' => 'country-de-uuid']);

        $payload = $customerRepo->getLastUpsert();

        $this->assertSame(
            $result->getShippingAddressId(),
            $result->getBillingAddressId(),
            'both defaults must share the shipping address ID when billing is null'
        );
        $this->assertSame($payload['defaultShippingAddressId'], $payload['defaultBillingAddressId']);
        $this->assertCount(1, $payload['addresses'], 'only one address row should be inserted');
    }

    /**
     * Both addresses already exist in the customer's address book → none inserted,
     * existing entity IDs used for defaults.
     */
    public function testExistingAddressesAreReusedWithoutInserting(): void
    {
        $shipping = $this->makeAddress('Bob', 'Builder', 'bob@example.com', 'Fix It Rd 3', 'Cologne', '50667', 'DE');
        $billing = $this->makeAddress('Bob', 'Builder', 'bob@example.com', 'Pay Ln 9', 'Cologne', '50668', 'DE');

        $shippingEntity = $this->makeAddressEntity('entity-shipping', $shipping->getId());
        $billingEntity = $this->makeAddressEntity('entity-billing', $billing->getId());

        $addressRepo = new FakeCustomerAddressSearchRepository([$shippingEntity, $billingEntity]);
        $customerRepo = new FakeCustomerRepository();

        $customer = $this->makeCustomer('customer-3');

        $synchronizer = new AddressSynchronizer($addressRepo, $customerRepo);
        $result = $synchronizer->syncAddresses($customer, $shipping, $billing, $this->makeSalesChannelContext(), []);

        $payload = $customerRepo->getLastUpsert();

        $this->assertSame('entity-shipping', $result->getShippingAddressId());
        $this->assertSame('entity-billing', $result->getBillingAddressId());
        $this->assertSame('entity-shipping', $payload['defaultShippingAddressId']);
        $this->assertSame('entity-billing', $payload['defaultBillingAddressId']);
        $this->assertArrayNotHasKey('addresses', $payload, 'no new address rows should be inserted when both already exist');
    }

    /**
     * Shipping exists, billing does not → only billing inserted;
     * shipping reuses the existing entity ID.
     */
    public function testPartialMatchInsertsOnlyMissingAddress(): void
    {
        $shipping = $this->makeAddress('Alice', 'Wonder', 'alice@example.com', 'Rabbit Hole 1', 'Dresden', '01067', 'DE');
        $billing = $this->makeAddress('Alice', 'Wonder', 'alice@example.com', 'Looking Glass 2', 'Dresden', '01069', 'DE');

        $shippingEntity = $this->makeAddressEntity('entity-shipping-existing', $shipping->getId());

        $addressRepo = new FakeCustomerAddressSearchRepository([$shippingEntity]);
        $customerRepo = new FakeCustomerRepository();

        $customer = $this->makeCustomer('customer-4');

        $synchronizer = new AddressSynchronizer($addressRepo, $customerRepo);
        $result = $synchronizer->syncAddresses($customer, $shipping, $billing, $this->makeSalesChannelContext(), ['DE' => 'country-de-uuid']);

        $payload = $customerRepo->getLastUpsert();

        $this->assertSame('entity-shipping-existing', $result->getShippingAddressId(), 'shipping must reuse existing entity');
        $this->assertNotSame('entity-shipping-existing', $result->getBillingAddressId(), 'billing must be a new entity');

        $this->assertSame('entity-shipping-existing', $payload['defaultShippingAddressId']);
        $this->assertSame($result->getBillingAddressId(), $payload['defaultBillingAddressId']);

        // Only one address inserted (billing)
        $this->assertCount(1, $payload['addresses'], 'exactly one new address row expected (the billing)');
        $this->assertSame($result->getBillingAddressId(), $payload['addresses'][0]['id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAddress(
        string $givenName,
        string $familyName,
        string $email,
        string $street,
        string $city,
        string $zip,
        string $country
    ): Address {
        return new Address($email, '', $givenName, $familyName, $street, $zip, $city, $country);
    }

    private function makeCustomer(string $id, ?string $salutationId = null): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        if ($salutationId !== null) {
            $customer->setSalutationId($salutationId);
        }

        return $customer;
    }

    private function makeAddressEntity(string $entityId, string $mollieId): CustomerAddressEntity
    {
        $entity = new CustomerAddressEntity();
        $entity->setId($entityId);
        $entity->setCustomFields([
            Address::CUSTOM_FIELDS_KEY => $mollieId,
        ]);

        return $entity;
    }

    private function makeSalesChannelContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn($this->context);

        return $context;
    }
}
