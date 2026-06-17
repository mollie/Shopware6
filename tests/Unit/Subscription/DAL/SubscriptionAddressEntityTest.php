<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

#[CoversClass(SubscriptionAddressEntity::class)]
final class SubscriptionAddressEntityTest extends TestCase
{
    public function testGettersReflectSetterValuesForAllProperties(): void
    {
        $entity = new SubscriptionAddressEntity();

        $entity->setSubscriptionId('subscription-id');
        $entity->setSalutationId('salutation-id');
        $entity->setTitle('Dr.');
        $entity->setFirstName('Jane');
        $entity->setLastName('Doe');
        $entity->setCompany('Example GmbH');
        $entity->setDepartment('Sales');
        $entity->setVatId('DE123456789');
        $entity->setStreet('Main Street 1');
        $entity->setZipcode('12345');
        $entity->setCity('Berlin');
        $entity->setCountryId('country-id');
        $entity->setCountryStateId('country-state-id');
        $entity->setPhoneNumber('+49 30 12345');
        $entity->setAdditionalAddressLine1('floor 4');
        $entity->setAdditionalAddressLine2('door 2');

        $country = new CountryEntity();
        $countryState = new CountryStateEntity();
        $salutation = new SalutationEntity();
        $subscription = new SubscriptionEntity();
        $billingSubscription = new SubscriptionEntity();
        $shippingSubscription = new SubscriptionEntity();

        $entity->setCountry($country);
        $entity->setCountryState($countryState);
        $entity->setSalutation($salutation);
        $entity->setSubscription($subscription);
        $entity->setBillingSubscription($billingSubscription);
        $entity->setShippingSubscription($shippingSubscription);

        $this->assertSame('subscription-id', $entity->getSubscriptionId());
        $this->assertSame('salutation-id', $entity->getSalutationId());
        $this->assertSame('Dr.', $entity->getTitle());
        $this->assertSame('Jane', $entity->getFirstName());
        $this->assertSame('Doe', $entity->getLastName());
        $this->assertSame('Example GmbH', $entity->getCompany());
        $this->assertSame('Sales', $entity->getDepartment());
        $this->assertSame('DE123456789', $entity->getVatId());
        $this->assertSame('Main Street 1', $entity->getStreet());
        $this->assertSame('12345', $entity->getZipcode());
        $this->assertSame('Berlin', $entity->getCity());
        $this->assertSame('country-id', $entity->getCountryId());
        $this->assertSame('country-state-id', $entity->getCountryStateId());
        $this->assertSame('+49 30 12345', $entity->getPhoneNumber());
        $this->assertSame('floor 4', $entity->getAdditionalAddressLine1());
        $this->assertSame('door 2', $entity->getAdditionalAddressLine2());
        $this->assertSame($country, $entity->getCountry());
        $this->assertSame($countryState, $entity->getCountryState());
        $this->assertSame($salutation, $entity->getSalutation());
        $this->assertSame($subscription, $entity->getSubscription());
        $this->assertSame($billingSubscription, $entity->getBillingSubscription());
        $this->assertSame($shippingSubscription, $entity->getShippingSubscription());
    }
}
