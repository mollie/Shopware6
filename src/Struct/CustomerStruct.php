<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class CustomerStruct extends Struct
{
    /** @var ?string */
    private $legacyCustomerId;

    /** @var array<mixed> */
    private $customerIds = [];

    /** @var ?string */
    private $preferredIdealIssuer;

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set(string $key, $value): void
    {
        $camelKey = (new CamelCaseToSnakeCaseNameConverter())->denormalize($key);
        $this->$camelKey = $value;
    }

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
     * @return string
     */
    public function getCustomerId(string $profileId, bool $testMode = false): string
    {
        return $this->customerIds[$profileId][$testMode ? 'test' : 'live'] ?? '';
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

    /**
     * @return array<mixed>
     */
    public function getCustomerIds(): array
    {
        return $this->customerIds;
    }

    /**
     * @param array<mixed> $customerIds
     */
    public function setCustomerIds(array $customerIds): void
    {
        $this->customerIds = $customerIds;
    }

    /**
     * @return string|null
     */
    public function getPreferredIdealIssuer(): ?string
    {
        return $this->preferredIdealIssuer;
    }

    /**
     * @param string|null $preferredIdealIssuer
     */
    public function setPreferredIdealIssuer(?string $preferredIdealIssuer): void
    {
        $this->preferredIdealIssuer = $preferredIdealIssuer;
    }
}
