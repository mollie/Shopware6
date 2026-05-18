<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ExpressMethod\AccountService;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(AccountService::class)]
final class AccountServiceTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelTestBehaviour;

    private AccountService $accountService;

    /** @var EntityRepository<\Shopware\Core\Checkout\Customer\CustomerCollection> */
    private EntityRepository $customerRepository;

    /** @var EntityRepository<\Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection> */
    private EntityRepository $customerAddressRepository;

    private SalesChannelContext $salesChannelContext;

    private string $paymentMethodId;

    public function setUp(): void
    {
        $this->accountService = $this->getContainer()->get(AccountService::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->customerAddressRepository = $this->getContainer()->get('customer_address.repository');
        $this->salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->paymentMethodId = $this->salesChannelContext->getPaymentMethod()->getId();
    }

    /**
     * New user, billing and shipping are the same address → one address entity created in
     * the DB; defaultBillingAddressId and defaultShippingAddressId both point to it.
     */
    public function testNewUserSameBillingAndShippingCreatesOneAddressInDb(): void
    {
        $email = 'express-same-' . Uuid::randomHex() . '@example.com';
        $address = $this->makeAddress($email, 'Main St 1', 'Berlin', '10115');

        $this->accountService->loginOrCreateAccount(
            $this->paymentMethodId,
            $address,
            $address,
            $this->salesChannelContext,
        );

        $customer = $this->findCustomerByEmail($email);
        $addresses = $this->loadAddressesForCustomer($customer->getId());

        $this->assertCount(1, $addresses, 'Exactly one address row must exist for identical billing/shipping');
        $this->assertSame($customer->getDefaultBillingAddressId(), $customer->getDefaultShippingAddressId(), 'Both defaults must point to the same address entity');
    }

    /**
     * New user, billing and shipping differ → two address entities created in the DB;
     * defaultBillingAddressId and defaultShippingAddressId point to different rows.
     */
    public function testNewUserDifferentBillingAndShippingCreatesTwoAddressesInDb(): void
    {
        $email = 'express-diff-' . Uuid::randomHex() . '@example.com';
        $billing = $this->makeAddress($email, 'Billing Ave 5', 'Munich', '80331');
        $shipping = $this->makeAddress($email, 'Shipping St 1', 'Berlin', '10115');

        $this->accountService->loginOrCreateAccount(
            $this->paymentMethodId,
            $billing,
            $shipping,
            $this->salesChannelContext,
        );

        $customer = $this->findCustomerByEmail($email);
        $addresses = $this->loadAddressesForCustomer($customer->getId());

        $this->assertCount(2, $addresses, 'Two address rows must be created for distinct billing/shipping');
        $this->assertNotSame($customer->getDefaultBillingAddressId(), $customer->getDefaultShippingAddressId(), 'Distinct addresses must have distinct default IDs');
    }

    /**
     * Existing user whose address already carries the matching express hash →
     * no new address row is inserted; both defaults are updated to the existing entity.
     */
    public function testExistingUserWithMatchingHashDoesNotInsertNewAddressInDb(): void
    {
        $email = 'express-match-' . Uuid::randomHex() . '@example.com';
        $address = $this->makeAddress($email, 'Main St 1', 'Berlin', '10115');

        $customerId = $this->createGuestCustomerWithAddress($email, $address->getId());

        $this->accountService->loginOrCreateAccount(
            $this->paymentMethodId,
            $address,
            $address,
            $this->salesChannelContext,
        );

        $addresses = $this->loadAddressesForCustomer($customerId);
        $customer = $this->findCustomerByEmail($email);

        $this->assertCount(1, $addresses, 'No new address row must be inserted when the hash already exists in the DB');
        $this->assertSame($customer->getDefaultBillingAddressId(), $customer->getDefaultShippingAddressId(), 'Both defaults must point to the same existing address');
    }

    /**
     * Existing user with an address that has no express hash → treated as no match;
     * one new address entity is created (billing = shipping) and both defaults are updated.
     */
    public function testExistingUserAddressWithoutHashCreatesNewAddressInDb(): void
    {
        $email = 'express-nohash-' . Uuid::randomHex() . '@example.com';
        $address = $this->makeAddress($email, 'Main St 1', 'Berlin', '10115');

        $customerId = $this->createGuestCustomerWithAddress($email, null);
        $customerBefore = $this->findCustomerByEmail($email);
        $oldDefaultId = $customerBefore->getDefaultBillingAddressId();

        $this->accountService->loginOrCreateAccount(
            $this->paymentMethodId,
            $address,
            $address,
            $this->salesChannelContext,
        );

        $addresses = $this->loadAddressesForCustomer($customerId);
        $customer = $this->findCustomerByEmail($email);

        $this->assertCount(2, $addresses, 'One new address must be created because the existing one has no express hash');
        $this->assertNotSame($oldDefaultId, $customer->getDefaultBillingAddressId(), 'Default must be updated to the new address entity');
        $this->assertSame($customer->getDefaultBillingAddressId(), $customer->getDefaultShippingAddressId(), 'Both defaults must point to the single new entity');
    }

    /**
     * Existing user with stale address hashes → two new address entities are created
     * in the DB for the new billing and shipping; defaults updated to the new IDs.
     */
    public function testExistingUserWithStaleHashGetsTwoNewAddressesInDb(): void
    {
        $email = 'express-stale-' . Uuid::randomHex() . '@example.com';

        $customerId = $this->createGuestCustomerWithAddress($email, 'stale-hash-that-matches-nothing');
        $customerBefore = $this->findCustomerByEmail($email);
        $oldDefaultId = $customerBefore->getDefaultBillingAddressId();

        $newBilling = $this->makeAddress($email, 'New Billing Ave 5', 'Munich', '80331');
        $newShipping = $this->makeAddress($email, 'New Shipping St 1', 'Berlin', '10115');

        $this->accountService->loginOrCreateAccount(
            $this->paymentMethodId,
            $newBilling,
            $newShipping,
            $this->salesChannelContext,
        );

        $addresses = $this->loadAddressesForCustomer($customerId);
        $customer = $this->findCustomerByEmail($email);

        $this->assertCount(3, $addresses, 'Two new address rows must be added; old one remains');
        $this->assertNotSame($oldDefaultId, $customer->getDefaultBillingAddressId(), 'Billing default must switch to the new entity');
        $this->assertNotSame($oldDefaultId, $customer->getDefaultShippingAddressId(), 'Shipping default must switch to the new entity');
        $this->assertNotSame($customer->getDefaultBillingAddressId(), $customer->getDefaultShippingAddressId(), 'New billing and shipping must be distinct entities');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAddress(string $email, string $street, string $city, string $zip): Address
    {
        return new Address($email, '', 'John', 'Doe', $street, $zip, $city, 'DE');
    }

    private function findCustomerByEmail(string $email): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var ?CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->first();
        $this->assertNotNull($customer, "Customer with email {$email} not found in DB");

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadAddressesForCustomer(string $customerId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));

        return $this->customerAddressRepository->search($criteria, Context::createDefaultContext())->getElements();
    }

    private function createGuestCustomerWithAddress(string $email, ?string $mollieHash): string
    {
        $context = Context::createDefaultContext();

        $salutation = $this->getContainer()->get('salutation.repository')
            ->search(new Criteria(), $context)
            ->first()
        ;

        $countryCriteria = new Criteria();
        $countryCriteria->addFilter(new EqualsFilter('iso', 'DE'));
        $country = $this->getContainer()->get('country.repository')
            ->search($countryCriteria, $context)
            ->first()
        ;

        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $salesChannel = $this->salesChannelContext->getSalesChannel();

        $addressPayload = [
            'id' => $addressId,
            'salutationId' => $salutation->getId(),
            'firstName' => 'John',
            'lastName' => 'Doe',
            'street' => 'Main St 1',
            'zipcode' => '10115',
            'city' => 'Berlin',
            'countryId' => $country->getId(),
            'customFields' => $mollieHash !== null ? [Address::CUSTOM_FIELDS_KEY => $mollieHash] : [],
        ];

        $this->customerRepository->upsert([[
            'id' => $customerId,
            'salesChannelId' => $salesChannel->getId(),
            'groupId' => $salesChannel->getCustomerGroupId(),
            'defaultPaymentMethodId' => $this->paymentMethodId,
            'salutationId' => $salutation->getId(),
            'customerNumber' => 'express-test-' . substr($customerId, 0, 8),
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'guest' => true,
            'active' => true,
            'doubleOptInRegistration' => false,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [$addressPayload],
        ]], $context);

        return $customerId;
    }
}
