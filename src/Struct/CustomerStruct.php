<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

class CustomerStruct
{
    /** @var ?string */
    private $legacyCustomerId;

    /** @var array */
    private $customerIds;

    /**
     * @return string|null
     */
    public function getLegacyCustomerId(): ?string
    {
        return $this->legacyCustomerId;
    }

    /**
     * @param string|null $legacyCustomerId
     */
    public function setLegacyCustomerId(?string $legacyCustomerId): void
    {
        $this->legacyCustomerId = $legacyCustomerId;
    }

    /**
     * @param string $profileId
     * @param bool $testMode
     * @return string|null
     */
    public function getCustomerId(string $profileId, bool $testMode = false): ?string
    {
        return $this->customerIds[$profileId][$testMode ? 'test' : 'live'];
    }

    /**
     * @param string $customerId
     * @param string $profileId
     * @param bool $testMode
     */
    public function setCustomerId(string $customerId, string $profileId, bool $testMode = false): void
    {
        $this->customerIds[$profileId][$testMode ? 'test' : 'live'] = $customerId;
    }
}
