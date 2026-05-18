<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the express_address_id in sync with the actual address data.
 *
 * When a customer edits their address, the custom field is recomputed from the
 * new field values using the same MD5 algorithm as Address::getId(). If the hash
 * changes, syncAddresses will no longer match the old entry and will create a fresh
 * address entity on the next express checkout.
 */
final class AddressChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'customer_address.repository')]
        private readonly EntityRepository $customerAddressRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'customer_address.written' => 'onCustomerAddressWritten',
        ];
    }

    public function onCustomerAddressWritten(EntityWrittenEvent $event): void
    {
        $addressIds = [];

        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $payload = $result->getPayload();
            $customFields = $payload['customFields'] ?? null;

            // If our key is already in the payload this is our own sync/recompute write — skip.
            if (is_array($customFields) && array_key_exists(Address::CUSTOM_FIELDS_KEY, $customFields)) {
                continue;
            }

            $addressIds[] = $result->getPrimaryKey();
        }

        if ($addressIds === []) {
            return;
        }

        $criteria = new Criteria($addressIds);
        $criteria->addAssociation('customer');
        $criteria->addAssociation('country');

        /** @var CustomerAddressCollection $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $event->getContext())->getEntities();

        $toUpdate = [];

        /** @var CustomerAddressEntity $address */
        foreach ($addresses as $address) {
            $customFields = $address->getCustomFields();
            $storedHash = $customFields[Address::CUSTOM_FIELDS_KEY] ?? null;

            if ($storedHash === null) {
                continue;
            }

            $newHash = Address::fromCustomerAddress($address)->getId();

            if ($newHash === $storedHash) {
                continue;
            }

            $toUpdate[] = [
                'id' => $address->getId(),
                'customFields' => [Address::CUSTOM_FIELDS_KEY => $newHash],
            ];
        }

        if ($toUpdate === []) {
            return;
        }

        $this->customerAddressRepository->upsert($toUpdate, $event->getContext());
    }
}
