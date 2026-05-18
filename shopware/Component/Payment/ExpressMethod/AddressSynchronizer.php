<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AddressSynchronizer implements AddressSynchronizerInterface
{
    public function __construct(
        #[Autowire(service: 'customer_address.repository')]
        private readonly EntityRepository $customerAddressRepository,
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
    ) {
    }

    /**
     * Finds or creates Shopware customer address rows for the given express-checkout addresses,
     * updates the customer's defaultShippingAddressId / defaultBillingAddressId, and returns
     * the resolved entity IDs.
     *
     * $billing === null means "billing is same as shipping" (Shopware storefront convention).
     */
    public function syncAddresses(
        CustomerEntity $customer,
        Address $shipping,
        ?Address $billing,
        SalesChannelContext $context,
        array $countryMap
    ): AddressSyncResult {
        $mollieIds = [$shipping->getId()];
        if ($billing !== null) {
            $mollieIds[] = $billing->getId();
        }
        $mollieIds = array_unique($mollieIds);

        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('customerId', $customer->getId()),
            new EqualsAnyFilter(
                'customFields.' . Address::CUSTOM_FIELDS_KEY,
                $mollieIds
            ),
        ]));

        $existing = $this->customerAddressRepository->search($criteria, $context->getContext());

        $foundShippingId = null;
        $foundBillingId = null;

        /** @var CustomerAddressEntity $entity */
        foreach ($existing->getElements() as $entity) {
            $customFields = $entity->getCustomFields();
            if ($customFields === null) {
                continue;
            }
            $mollieId = $customFields[Address::CUSTOM_FIELDS_KEY] ?? null;
            if ($mollieId === null) {
                continue;
            }
            if ($mollieId === $shipping->getId()) {
                $foundShippingId = $entity->getId();
            }
            if ($billing !== null && $mollieId === $billing->getId()) {
                $foundBillingId = $entity->getId();
            }
        }

        $billingIsSameAsShipping = $billing !== null && $billing->getId() === $shipping->getId();

        $addressesToInsert = [];

        if ($foundShippingId === null || ($billing !== null && ! $billingIsSameAsShipping && $foundBillingId === null)) {
            if ($foundShippingId === null) {
                $foundShippingId = Uuid::randomHex();
                $addressesToInsert[] = $this->buildAddressPayload(
                    $foundShippingId,
                    $customer->getId(),
                    $customer->getSalutationId(),
                    $shipping,
                    $countryMap
                );
            }

            if ($billing !== null && ! $billingIsSameAsShipping && $foundBillingId === null) {
                $foundBillingId = Uuid::randomHex();
                $addressesToInsert[] = $this->buildAddressPayload(
                    $foundBillingId,
                    $customer->getId(),
                    $customer->getSalutationId(),
                    $billing,
                    $countryMap
                );
            }
        }

        // billing === null or billing identical to shipping → reuse the single shipping entity for both defaults
        if ($billing === null || $billingIsSameAsShipping) {
            $foundBillingId = $foundShippingId;
        }

        $customerPayload = [
            'id' => $customer->getId(),
            'defaultShippingAddressId' => $foundShippingId,
            'defaultBillingAddressId' => $foundBillingId,
        ];
        if ($addressesToInsert !== []) {
            $customerPayload['addresses'] = $addressesToInsert;
        }

        $this->customerRepository->upsert([$customerPayload], $context->getContext());

        return new AddressSyncResult((string) $foundShippingId, (string) $foundBillingId);
    }

    /**
     * @param array<string, string> $countryMap ISO → Shopware country UUID
     *
     * @return array<string, mixed>
     */
    private function buildAddressPayload(
        string $id,
        string $customerId,
        ?string $salutationId,
        Address $address,
        array $countryMap
    ): array {
        $payload = [
            'id' => $id,
            'customerId' => $customerId,
            'firstName' => $address->getGivenName(),
            'lastName' => $address->getFamilyName(),
            'street' => $address->getStreetAndNumber(),
            'zipcode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'countryId' => $countryMap[$address->getCountry()] ?? null,
            'customFields' => [
                Address::CUSTOM_FIELDS_KEY => $address->getId(),
            ],
        ];

        if ($salutationId !== null) {
            $payload['salutationId'] = $salutationId;
        }

        $streetAdditional = $address->getStreetAdditional();
        if ($streetAdditional !== '') {
            $payload['additionalAddressLine1'] = $streetAdditional;
        }

        $phone = $address->getPhone();
        if ($phone !== '') {
            $payload['phoneNumber'] = $phone;
        }

        return $payload;
    }
}
