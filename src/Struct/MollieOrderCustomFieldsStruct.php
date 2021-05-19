<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;


/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MollieOrderCustomFieldsStruct
{
    public const MOLLIE_KEY = 'mollie_payments';

    public const ORDER_KEY = 'order_id';

    /** @var string|null */
    private $mollieOrderId;

    public function __construct(array $orderCustomFields = [])
    {
        if (!empty($orderCustomFields)) {
            $this->setCustomFields($orderCustomFields);
        }
    }

    /**
     * @return string|null
     */
    public function getMollieOrderId(): ?string
    {
        return $this->mollieOrderId;
    }

    /**
     * @param string|null $mollieOrderId
     */
    public function setMollieOrderId(?string $mollieOrderId): void
    {
        $this->mollieOrderId = $mollieOrderId;
    }

    private function setCustomFields(array $customFields): void
    {
        if (!isset($customFields[self::MOLLIE_KEY])) {
            return;
        }

        if (isset($customFields[self::MOLLIE_KEY][self::ORDER_KEY])) {
            $this->setMollieOrderId((string)$customFields[self::MOLLIE_KEY][self::ORDER_KEY]);
        }
    }

    public function setMollieCustomFields(array $customFields = []): array
    {
        if (!empty($this->getMollieOrderId())) {
            $customFields = $this->setKey($customFields, self::ORDER_KEY, $this->getMollieOrderId());
        }

        return $customFields;
    }

    public function setKey(array $customFields, string $key, string $value): array
    {
        if (!isset($customFields[self::MOLLIE_KEY])) {
            $customFields[self::MOLLIE_KEY] = [];
        }

        $customFields[self::MOLLIE_KEY][$key] = $value;

        return $customFields;
    }
}
