<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Customer;

use Shopware\Core\Framework\Struct\Struct;

final class Customer extends Struct
{
    /**
     * @param array<string> $customerIds
     */
    public function __construct(private array $customerIds = [])
    {
    }

    /**
     * @return string[]
     */
    public function getCustomerIds(): array
    {
        return $this->customerIds;
    }

    public function setCustomerId(string $profileId, string $customerId): void
    {
        $this->customerIds[$profileId] = $customerId;
    }

    public function getForProfileId(string $profileId): ?string
    {
        return $this->customerIds[$profileId] ?? null;
    }

    /**
     * @return array<string, array<string>>
     */
    public function toArray(): array
    {
        return [
            'customer_ids' => $this->customerIds,
        ];
    }
}
