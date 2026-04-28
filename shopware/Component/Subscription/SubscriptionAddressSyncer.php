<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionAddressSyncer implements SubscriptionAddressSyncerInterface
{
    /**
     * @param EntityRepository<CustomerAddressCollection<CustomerAddressEntity>> $customerAddressRepository
     */
    public function __construct(
        #[Autowire(service: 'customer_address.repository')]
        private readonly EntityRepository $customerAddressRepository,
    ) {
    }

    public function syncFromSubscription(SubscriptionEntity $subscription, Context $context): array
    {
        $customerId = (string) $subscription->getCustomerId();

        $billingAddress = $subscription->getBillingAddress();
        $shippingAddress = $subscription->getShippingAddress();

        if (! $billingAddress instanceof SubscriptionAddressEntity) {
            throw new \RuntimeException(sprintf('Subscription %s has no billing address', $subscription->getId()));
        }
        if (! $shippingAddress instanceof SubscriptionAddressEntity) {
            throw new \RuntimeException(sprintf('Subscription %s has no shipping address', $subscription->getId()));
        }

        $billingId = $this->ensureCustomerAddress($customerId, $billingAddress, $context);

        if ($this->buildAddressId($customerId, $shippingAddress) === $billingId) {
            return [
                'billingAddressId' => $billingId,
                'shippingAddressId' => $billingId,
            ];
        }

        return [
            'billingAddressId' => $billingId,
            'shippingAddressId' => $this->ensureCustomerAddress($customerId, $shippingAddress, $context),
        ];
    }

    private function ensureCustomerAddress(string $customerId, SubscriptionAddressEntity $subAddress, Context $context): string
    {
        $candidateId = $this->buildAddressId($customerId, $subAddress);

        $existing = $this->customerAddressRepository->searchIds(new Criteria([$candidateId]), $context)->firstId();
        if ($existing !== null) {
            return (string) $existing;
        }

        $this->customerAddressRepository->upsert([[
            'id' => $candidateId,
            'customerId' => $customerId,
            'salutationId' => $subAddress->getSalutationId(),
            'firstName' => (string) $subAddress->getFirstName(),
            'lastName' => (string) $subAddress->getLastName(),
            'company' => $subAddress->getCompany(),
            'department' => $subAddress->getDepartment(),
            'street' => (string) $subAddress->getStreet(),
            'zipcode' => (string) $subAddress->getZipcode(),
            'city' => (string) $subAddress->getCity(),
            'countryId' => (string) $subAddress->getCountryId(),
            'countryStateId' => $subAddress->getCountryStateId(),
            'phoneNumber' => $subAddress->getPhoneNumber(),
            'additionalAddressLine1' => $subAddress->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $subAddress->getAdditionalAddressLine2(),
        ]], $context);

        return $candidateId;
    }

    private function buildAddressId(string $customerId, SubscriptionAddressEntity $subAddress): string
    {
        $hash = implode('|', [
            $customerId,
            (string) $subAddress->getSalutationId(),
            (string) $subAddress->getFirstName(),
            (string) $subAddress->getLastName(),
            (string) $subAddress->getCompany(),
            (string) $subAddress->getDepartment(),
            (string) $subAddress->getStreet(),
            (string) $subAddress->getZipcode(),
            (string) $subAddress->getCity(),
            (string) $subAddress->getCountryId(),
            (string) $subAddress->getCountryStateId(),
            (string) $subAddress->getPhoneNumber(),
            (string) $subAddress->getAdditionalAddressLine1(),
            (string) $subAddress->getAdditionalAddressLine2(),
        ]);

        return Uuid::fromStringToHex($hash);
    }
}
