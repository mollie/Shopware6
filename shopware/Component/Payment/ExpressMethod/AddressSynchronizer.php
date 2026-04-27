<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Canonical service for express-checkout address synchronisation.
 *
 * Shared by Apple Pay Direct and PayPal Express. Finds existing customer
 * addresses that match the express-checkout payload (via the MD5
 * `express_address_id` custom field), creates missing rows, and updates the
 * customer's defaultBillingAddressId / defaultShippingAddressId so that the
 * subsequent checkout step picks up the correct addresses.
 *
 * When $billing is null Shopware's "billing same as shipping" convention
 * applies: the shipping address is used for both defaults.
 */
final class AddressSynchronizer
{
    /**
     * @param EntityRepository<CustomerAddressCollection<CustomerAddressEntity>> $customerAddressRepository
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     * @param EntityRepository<CountryCollection<CountryEntity>> $countryRepository
     */
    public function __construct(
        #[Autowire(service: 'customer_address.repository')]
        private readonly EntityRepository $customerAddressRepository,
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
        #[Autowire(service: 'country.repository')]
        private readonly EntityRepository $countryRepository,
    ) {
    }

    /**
     * Syncs the express-checkout addresses for $customer.
     *
     * Finds existing Shopware customer-address rows that match the Mollie
     * express-address hash. Creates any missing rows. Updates the customer's
     * defaultShippingAddressId and defaultBillingAddressId.
     *
     * @param Address      $shipping the shipping address from the express payload
     * @param Address|null $billing  the billing address, or null when billing === shipping
     */
    public function syncAddresses(
        CustomerEntity $customer,
        Address $shipping,
        ?Address $billing,
        SalesChannelContext $context,
    ): AddressSyncResult {
        $mollieIds = array_unique(
            $billing !== null
                ? [$shipping->getId(), $billing->getId()]
                : [$shipping->getId()]
        );

        $existing = $this->findExistingAddresses($customer->getId(), $mollieIds, $context);

        $defaultShippingId = null;
        $defaultBillingId = null;

        /** @var CustomerAddressEntity $addr */
        foreach ($existing as $addr) {
            $customFields = $addr->getCustomFields();
            if ($customFields === null) {
                continue;
            }
            $mollieId = $customFields[Mollie::EXTENSION][Address::CUSTOM_FIELDS_KEY] ?? null;

            if ($mollieId === $shipping->getId()) {
                $defaultShippingId = $addr->getId();
            }
            if ($billing !== null && $mollieId === $billing->getId()) {
                $defaultBillingId = $addr->getId();
            }
        }

        $countryIsoMap = $this->resolveCountryIds($shipping, $billing, $context);
        $newAddresses = [];

        if ($defaultShippingId === null) {
            $defaultShippingId = Uuid::randomHex();
            $newAddresses[] = $this->buildAddressArray($defaultShippingId, $customer, $shipping, $countryIsoMap);
        }

        if ($billing !== null && $defaultBillingId === null) {
            $defaultBillingId = Uuid::randomHex();
            $newAddresses[] = $this->buildAddressArray($defaultBillingId, $customer, $billing, $countryIsoMap);
        }

        // $billing === null → Shopware "billing same as shipping" — reuse shipping ID
        if ($billing === null && $defaultBillingId === null) {
            $defaultBillingId = $defaultShippingId;
        }

        $customerData = [
            'id' => $customer->getId(),
            'defaultShippingAddressId' => $defaultShippingId,
            'defaultBillingAddressId' => $defaultBillingId,
        ];
        if (count($newAddresses) > 0) {
            $customerData['addresses'] = $newAddresses;
        }

        $this->customerRepository->upsert([$customerData], $context->getContext());

        return new AddressSyncResult((string) $defaultShippingId, (string) $defaultBillingId);
    }

    /**
     * @param list<string> $mollieIds
     * @return iterable<CustomerAddressEntity>
     */
    private function findExistingAddresses(string $customerId, array $mollieIds, SalesChannelContext $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('customerId', $customerId),
            new EqualsAnyFilter(
                'customFields.' . Mollie::EXTENSION . '.' . Address::CUSTOM_FIELDS_KEY,
                $mollieIds
            ),
        ]));

        return $this->customerAddressRepository
            ->search($criteria, $context->getContext())
            ->getEntities();
    }

    /**
     * @return array<string, string>  ISO code → Shopware country UUID
     */
    private function resolveCountryIds(Address $shipping, ?Address $billing, SalesChannelContext $context): array
    {
        $isoCodes = [$shipping->getCountry()];
        if ($billing !== null) {
            $isoCodes[] = $billing->getCountry();
        }
        $isoCodes = array_unique($isoCodes);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('iso', $isoCodes));
        $result = $this->countryRepository->search($criteria, $context->getContext());

        $map = [];
        /** @var CountryEntity $country */
        foreach ($result->getEntities() as $country) {
            $map[$country->getIso()] = $country->getId();
        }

        return $map;
    }

    /**
     * @param array<string, string> $countryIsoMap
     * @return array<string, mixed>
     */
    private function buildAddressArray(
        string $addressId,
        CustomerEntity $customer,
        Address $address,
        array $countryIsoMap,
    ): array {
        $data = [
            'id' => $addressId,
            'customerId' => $customer->getId(),
            'countryId' => $countryIsoMap[$address->getCountry()] ?? null,
            'firstName' => $address->getGivenName(),
            'lastName' => $address->getFamilyName(),
            'street' => $address->getStreetAndNumber(),
            'zipcode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'customFields' => [
                Mollie::EXTENSION => [
                    Address::CUSTOM_FIELDS_KEY => $address->getId(),
                ],
            ],
        ];

        $salutationId = $customer->getSalutationId();
        if ($salutationId !== null) {
            $data['salutationId'] = $salutationId;
        }

        if ($address->getStreetAdditional() !== '') {
            $data['additionalAddressLine1'] = $address->getStreetAdditional();
        }
        if ($address->getPhone() !== '') {
            $data['phoneNumber'] = $address->getPhone();
        }
        if ($address->getOrganizationName() !== '') {
            $data['company'] = $address->getOrganizationName();
        }

        return $data;
    }
}
