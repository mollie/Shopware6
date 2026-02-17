<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Customer;

use Mollie\Shopware\Component\Mollie\Mode;
use Shopware\Core\Framework\Struct\Struct;

final class Customer extends Struct
{
    /**
     * @param array<mixed> $customerIds
     */
    public function __construct(private array $customerIds = [])
    {
    }

    /**
     * @return array<mixed>
     */
    public function getCustomerIds(): array
    {
        return $this->customerIds;
    }

    public function setCustomerId(string $profileId, Mode $mode, string $customerId): void
    {
        $this->customerIds[$profileId][$mode->value] = $customerId;
    }

    public function getForProfileId(string $profileId, Mode $mode): ?string
    {
        return $this->customerIds[$profileId][$mode->value] ?? null;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'customer_ids' => $this->customerIds,
        ];
    }
}
