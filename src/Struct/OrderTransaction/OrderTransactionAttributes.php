<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\OrderTransaction;

use Kiener\MolliePayments\Service\CustomFieldsInterface;

class OrderTransactionAttributes
{
    /**
     * @var null|string
     */
    private $mollieOrderId;

    /**
     * @var null|string
     */
    private $molliePaymentId;

    /**
     * @var null|string
     */
    private $thirdPartyPaymentId;


    /**
     * @param array<string,mixed> $orderCustomFields
     */
    public function __construct(array $orderCustomFields = [])
    {
        if (!empty($orderCustomFields)) {
            $this->setCustomFields($orderCustomFields);
        }
    }

    /**
     * @return null|string
     */
    public function getMollieOrderId(): ?string
    {
        return $this->mollieOrderId;
    }

    /**
     * @param null|string $mollieOrderId
     */
    public function setMollieOrderId(?string $mollieOrderId): void
    {
        $this->mollieOrderId = $mollieOrderId;
    }

    /**
     * @return null|string
     */
    public function getMolliePaymentId(): ?string
    {
        return $this->molliePaymentId;
    }

    /**
     * @param null|string $molliePaymentId
     */
    public function setMolliePaymentId(?string $molliePaymentId): void
    {
        $this->molliePaymentId = $molliePaymentId;
    }

    /**
     * @return null|string
     */
    public function getThirdPartyPaymentId(): ?string
    {
        return $this->thirdPartyPaymentId;
    }

    /**
     * @param null|string $thirdPartyPaymentId
     */
    public function setThirdPartyPaymentId(?string $thirdPartyPaymentId): void
    {
        $this->thirdPartyPaymentId = $thirdPartyPaymentId;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        if (empty($this->mollieOrderId)) {
            return [];
        }

        return [
            CustomFieldsInterface::ORDER_KEY => $this->mollieOrderId,
            CustomFieldsInterface::PAYMENT_KEY => $this->molliePaymentId,
            CustomFieldsInterface::THIRD_PARTY_PAYMENT_KEY => $this->thirdPartyPaymentId,
        ];
    }

    /**
     * @param array<string,mixed> $customFields
     */
    private function setCustomFields(array $customFields): void
    {
        if (!isset($customFields[CustomFieldsInterface::MOLLIE_KEY])) {
            return;
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY])) {
            $this->setMollieOrderId((string)$customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY]);
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYMENT_KEY])) {
            $this->setMolliePaymentId((string)$customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYMENT_KEY]);
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::THIRD_PARTY_PAYMENT_KEY])) {
            $this->setThirdPartyPaymentId((string)$customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::THIRD_PARTY_PAYMENT_KEY]);
        }
    }
}
