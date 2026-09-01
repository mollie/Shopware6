<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * A subscription's addresses are deduplicated by this id: two addresses that a customer would call
 * the same must produce the same id, and anything a shipping label depends on must produce a
 * different one.
 */
#[CoversClass(SubscriptionAddressId::class)]
final class SubscriptionAddressIdTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-1';

    public function testTheSameAddressOfTheSameCustomerGetsTheSameId(): void
    {
        $first = new SubscriptionAddressId(self::CUSTOMER_ID, $this->address());
        $second = new SubscriptionAddressId(self::CUSTOMER_ID, $this->address());

        $this->assertSame((string) $first, (string) $second);
    }

    /**
     * Two customers with the same address must not share an address record.
     */
    public function testTheSameAddressOfAnotherCustomerGetsItsOwnId(): void
    {
        $mine = new SubscriptionAddressId(self::CUSTOMER_ID, $this->address());
        $theirs = new SubscriptionAddressId('customer-2', $this->address());

        $this->assertNotSame((string) $mine, (string) $theirs);
    }

    public function testTheIdIsAUuidTheDalCanStore(): void
    {
        $addressId = (string) new SubscriptionAddressId(self::CUSTOMER_ID, $this->address());

        $this->assertTrue(Uuid::isValid($addressId));
    }

    /**
     * @param \Closure(SubscriptionAddressEntity): void $change
     */
    #[DataProvider('addressChangeProvider')]
    public function testAChangedAddressGetsItsOwnId(\Closure $change): void
    {
        $original = new SubscriptionAddressId(self::CUSTOMER_ID, $this->address());

        $changedAddress = $this->address();
        $change($changedAddress);

        $this->assertNotSame((string) $original, (string) new SubscriptionAddressId(self::CUSTOMER_ID, $changedAddress));
    }

    /**
     * Every field a parcel is delivered by has to change the id, or a moved customer would keep
     * receiving their subscription at the old address.
     *
     * @return array<string, array{\Closure(SubscriptionAddressEntity): void}>
     */
    public static function addressChangeProvider(): array
    {
        return [
            'moved to another street' => [fn (SubscriptionAddressEntity $a) => $a->setStreet('Another Street 2')],
            'moved to another zip code' => [fn (SubscriptionAddressEntity $a) => $a->setZipcode('20095')],
            'moved to another city' => [fn (SubscriptionAddressEntity $a) => $a->setCity('Hamburg')],
            'moved to another country' => [fn (SubscriptionAddressEntity $a) => $a->setCountryId('country-nl')],
            'moved to another state' => [fn (SubscriptionAddressEntity $a) => $a->setCountryStateId('state-2')],
            'married and renamed' => [fn (SubscriptionAddressEntity $a) => $a->setLastName('Smith')],
            'first name corrected' => [fn (SubscriptionAddressEntity $a) => $a->setFirstName('Janet')],
            'another salutation' => [fn (SubscriptionAddressEntity $a) => $a->setSalutationId('salutation-2')],
            'another company' => [fn (SubscriptionAddressEntity $a) => $a->setCompany('Other Corp')],
            'another department' => [fn (SubscriptionAddressEntity $a) => $a->setDepartment('Sales')],
            'another phone number' => [fn (SubscriptionAddressEntity $a) => $a->setPhoneNumber('+49 40 654321')],
            'another address line' => [fn (SubscriptionAddressEntity $a) => $a->setAdditionalAddressLine1('Backyard')],
            'another second address line' => [fn (SubscriptionAddressEntity $a) => $a->setAdditionalAddressLine2('Ring twice')],
        ];
    }

    /**
     * The VAT id does not change where the parcel goes, so it must not split the address record.
     */
    public function testAChangedVatIdKeepsTheSameId(): void
    {
        $original = new SubscriptionAddressId(self::CUSTOMER_ID, $this->address());

        $changedAddress = $this->address();
        $changedAddress->setVatId('DE999999999');

        $this->assertSame((string) $original, (string) new SubscriptionAddressId(self::CUSTOMER_ID, $changedAddress));
    }

    private function address(): SubscriptionAddressEntity
    {
        $address = new SubscriptionAddressEntity();
        $address->setId('subscription-address-1');
        $address->setSalutationId('salutation-1');
        $address->setFirstName('Jane');
        $address->setLastName('Doe');
        $address->setCompany('Acme Corp');
        $address->setDepartment('Purchasing');
        $address->setStreet('Some Street 1');
        $address->setZipcode('10115');
        $address->setCity('Berlin');
        $address->setCountryId('country-de');
        $address->setCountryStateId('state-1');
        $address->setPhoneNumber('+49 30 123456');
        $address->setAdditionalAddressLine1('Second floor');
        $address->setAdditionalAddressLine2('c/o Doe');

        return $address;
    }
}
