<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ExpressMethod\AddressChangedSubscriber;
use Mollie\Shopware\Unit\Fake\FakeCustomerAddressSearchRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\Country\CountryEntity;

#[CoversClass(AddressChangedSubscriber::class)]
final class AddressChangedSubscriberTest extends TestCase
{
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    /**
     * INSERT results must never trigger recomputation.
     */
    public function testInsertIsIgnored(): void
    {
        $repo = new FakeCustomerAddressSearchRepository([]);

        $subscriber = new AddressChangedSubscriber($repo);
        $event = $this->makeEvent([
            $this->makeResult('addr-1', ['street' => 'New St 1'], EntityWriteResult::OPERATION_INSERT),
        ]);

        $subscriber->onCustomerAddressWritten($event);

        $this->assertEmpty($repo->getUpserts(), 'INSERT must not trigger recomputation');
    }

    /**
     * When our own sync/recompute write includes express_address_id in the payload, skip it.
     */
    public function testOwnSyncWriteIsIgnored(): void
    {
        $repo = new FakeCustomerAddressSearchRepository([]);

        $subscriber = new AddressChangedSubscriber($repo);
        $event = $this->makeEvent([
            $this->makeResult('addr-2', ['customFields' => [Address::CUSTOM_FIELDS_KEY => 'abc123']], EntityWriteResult::OPERATION_UPDATE),
        ]);

        $subscriber->onCustomerAddressWritten($event);

        $this->assertEmpty($repo->getUpserts(), 'Our own sync write must not trigger recomputation');
    }

    /**
     * Customer edits address → hash recomputed → custom field updated with new hash.
     */
    public function testCustomerEditRecomputesHash(): void
    {
        $oldHash = md5('John-Doe-john@example.com-Old St 1--10115-Berlin-DE');
        $expectedNewHash = md5('John-Doe-john@example.com-New St 99--10115-Berlin-DE');

        $addressEntity = $this->makeAddressEntity('addr-3', 'John', 'Doe', 'New St 99', '10115', 'Berlin', 'DE', 'john@example.com', $oldHash);
        $repo = new FakeCustomerAddressSearchRepository([$addressEntity]);

        $subscriber = new AddressChangedSubscriber($repo);
        $event = $this->makeEvent([
            $this->makeResult('addr-3', ['street' => 'New St 99'], EntityWriteResult::OPERATION_UPDATE),
        ]);

        $subscriber->onCustomerAddressWritten($event);

        $upserts = $repo->getUpserts();
        $this->assertCount(1, $upserts);
        $this->assertSame('addr-3', $upserts[0]['id']);
        $this->assertSame($expectedNewHash, $upserts[0]['customFields'][Address::CUSTOM_FIELDS_KEY]);
    }

    /**
     * Address without express_address_id in custom fields → no update.
     */
    public function testAddressWithoutExpressIdIsSkipped(): void
    {
        $addressEntity = $this->makeAddressEntity('addr-4', 'Jane', 'Smith', 'Main St 1', '20095', 'Hamburg', 'DE', 'jane@example.com', null);
        $repo = new FakeCustomerAddressSearchRepository([$addressEntity]);

        $subscriber = new AddressChangedSubscriber($repo);
        $event = $this->makeEvent([
            $this->makeResult('addr-4', ['city' => 'Hamburg'], EntityWriteResult::OPERATION_UPDATE),
        ]);

        $subscriber->onCustomerAddressWritten($event);

        $this->assertEmpty($repo->getUpserts(), 'Address without express_address_id must not be updated');
    }

    /**
     * Hash unchanged after edit (e.g. only a non-hashed field changed) → no update.
     */
    public function testUnchangedHashDoesNotTriggerUpdate(): void
    {
        $hash = md5('Bob-Builder-bob@example.com-Fix It Rd 3--50667-Cologne-DE');
        $addressEntity = $this->makeAddressEntity('addr-5', 'Bob', 'Builder', 'Fix It Rd 3', '50667', 'Cologne', 'DE', 'bob@example.com', $hash);
        $repo = new FakeCustomerAddressSearchRepository([$addressEntity]);

        $subscriber = new AddressChangedSubscriber($repo);
        $event = $this->makeEvent([
            // payload only contains a non-hashed field; the actual address data hasn't changed
            $this->makeResult('addr-5', ['additionalAddressLine2' => 'Floor 2'], EntityWriteResult::OPERATION_UPDATE),
        ]);

        $subscriber->onCustomerAddressWritten($event);

        $this->assertEmpty($repo->getUpserts(), 'No upsert when recomputed hash equals stored hash');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param EntityWriteResult[] $results */
    private function makeEvent(array $results): EntityWrittenEvent
    {
        return new EntityWrittenEvent(CustomerAddressDefinition::ENTITY_NAME, $results, $this->context);
    }

    private function makeResult(string $id, array $payload, string $operation): EntityWriteResult
    {
        return new EntityWriteResult($id, $payload, CustomerAddressDefinition::ENTITY_NAME, $operation);
    }

    private function makeAddressEntity(
        string $id,
        string $firstName,
        string $lastName,
        string $street,
        string $zipcode,
        string $city,
        string $countryIso,
        string $email,
        ?string $expressHash,
    ): CustomerAddressEntity {
        $country = new CountryEntity();
        $country->setId('country-' . $countryIso);
        $country->setIso($countryIso);

        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setEmail($email);

        $address = new CustomerAddressEntity();
        $address->setId($id);
        $address->setFirstName($firstName);
        $address->setLastName($lastName);
        $address->setStreet($street);
        $address->setZipcode($zipcode);
        $address->setCity($city);
        $address->setCountry($country);
        $address->setCustomer($customer);
        $address->setCustomFields($expressHash !== null ? [Address::CUSTOM_FIELDS_KEY => $expressHash] : []);

        return $address;
    }
}
