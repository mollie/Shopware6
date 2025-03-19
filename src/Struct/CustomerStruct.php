<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class CustomerStruct extends Struct
{
    public const LIVE_MODE = 'live';
    public const TEST_MODE = 'test';

    /**
     * @var ?string
     */
    private $legacyCustomerId;

    /**
     * @var array<mixed>
     */
    private $customerIds = [];

    /**
     * @var string
     */
    private $creditCardToken;

    /**
     * @var bool
     */
    private $shouldSaveCardDetail;

    /**
     * TODO: we need to get rid off this one day, no magic -> we need to explicitly load the values from the MySQL JSON
     */
    public function __set(string $key, $value): void
    {
        $camelKey = (new CamelCaseToSnakeCaseNameConverter())->denormalize($key);
        $this->{$camelKey} = $value;
    }

    public function getLegacyCustomerId(): ?string
    {
        return $this->legacyCustomerId;
    }

    public function setLegacyCustomerId(?string $legacyCustomerId): void
    {
        $this->legacyCustomerId = $legacyCustomerId;
    }

    public function getCustomerId(string $profileId, bool $testMode = false): string
    {
        return $this->customerIds[$profileId][$testMode ? self::TEST_MODE : self::LIVE_MODE] ?? '';
    }

    public function setCustomerId(string $customerId, string $profileId, bool $testMode = false): void
    {
        $this->customerIds[$profileId][$testMode ? self::TEST_MODE : self::LIVE_MODE] = $customerId;
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

    public function setCreditCardToken(?string $creditCardToken): void
    {
        $this->creditCardToken = (string) $creditCardToken;
    }

    /**
     * @return array<mixed>
     */
    public function toCustomFieldsArray(): array
    {
        $mollieData = [
            'customer_ids' => [],
        ];

        $oldLegacyCustomerID = (string) $this->legacyCustomerId;
        $legacyCustomerIdShouldBeRemoved = false;

        foreach ($this->customerIds as $profileID => $values) {
            $liveKey = (array_key_exists(self::LIVE_MODE, $values)) ? $values[self::LIVE_MODE] : '';
            $testKey = (array_key_exists(self::TEST_MODE, $values)) ? $values[self::TEST_MODE] : '';

            $mollieData['customer_ids'][$profileID] = [
                'live' => (string) $liveKey,
                'test' => (string) $testKey,
            ];

            // if our existing old legacy customer ID
            // is either the TEST or LIVE key in any of our profiles, then we need
            // to remove it
            if (! empty($oldLegacyCustomerID) && ($liveKey === $oldLegacyCustomerID || $testKey === $oldLegacyCustomerID)) {
                $legacyCustomerIdShouldBeRemoved = true;
            }
        }

        if (! empty((string) $this->creditCardToken)) {
            $mollieData['credit_card_token'] = (string) $this->creditCardToken;
        }

        if (! empty($this->shouldSaveCardDetail)) {
            $mollieData['shouldSaveCardDetail'] = $this->shouldSaveCardDetail;
        }

        $fullCustomField = [
            CustomFieldsInterface::MOLLIE_KEY => $mollieData,
        ];

        // now either reset our old customer ID
        // or keep it with its data.
        // if its neither of those, just don't add it
        if ($legacyCustomerIdShouldBeRemoved) {
            $fullCustomField['customer_id'] = null;
        } elseif (! empty($oldLegacyCustomerID)) {
            $fullCustomField['customer_id'] = $oldLegacyCustomerID;
        }

        return $fullCustomField;
    }
}
