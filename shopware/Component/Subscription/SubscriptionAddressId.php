<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Shopware\Core\Framework\Uuid\Uuid;

final class SubscriptionAddressId implements \Stringable
{
    private readonly string $value;

    public function __construct(string $customerId, SubscriptionAddressEntity $address)
    {
        $hash = implode('|', [
            $customerId,
            (string) $address->getSalutationId(),
            (string) $address->getFirstName(),
            (string) $address->getLastName(),
            (string) $address->getCompany(),
            (string) $address->getDepartment(),
            (string) $address->getStreet(),
            (string) $address->getZipcode(),
            (string) $address->getCity(),
            (string) $address->getCountryId(),
            (string) $address->getCountryStateId(),
            (string) $address->getPhoneNumber(),
            (string) $address->getAdditionalAddressLine1(),
            (string) $address->getAdditionalAddressLine2(),
        ]);

        $this->value = Uuid::fromStringToHex($hash);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
