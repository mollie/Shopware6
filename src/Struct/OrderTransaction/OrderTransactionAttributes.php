<?php
declare(strict_types=1);

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

    private ?int $webhookReceived;

    /**
     * @param array<string,mixed> $orderCustomFields
     */
    public function __construct(array $orderCustomFields = [])
    {
        if (! empty($orderCustomFields)) {
            $this->setCustomFields($orderCustomFields);
        }
    }

    public function getMollieOrderId(): ?string
    {
        return $this->mollieOrderId;
    }

    public function setMollieOrderId(?string $mollieOrderId): void
    {
        $this->mollieOrderId = $mollieOrderId;
    }

    public function getMolliePaymentId(): ?string
    {
        return $this->molliePaymentId;
    }

    public function setMolliePaymentId(?string $molliePaymentId): void
    {
        $this->molliePaymentId = $molliePaymentId;
    }

    public function getThirdPartyPaymentId(): ?string
    {
        return $this->thirdPartyPaymentId;
    }

    public function setThirdPartyPaymentId(?string $thirdPartyPaymentId): void
    {
        $this->thirdPartyPaymentId = $thirdPartyPaymentId;
    }

    public function getWebhookReceived(): ?int
    {
        return $this->webhookReceived;
    }

    public function setWebhookReceived(?int $webhookReceived): void
    {
        $this->webhookReceived = $webhookReceived;
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
            CustomFieldsInterface::WEBHOOK_RECEIVED => $this->webhookReceived,
        ];
    }

    /**
     * @param array<string,mixed> $customFields
     */
    private function setCustomFields(array $customFields): void
    {
        if (! isset($customFields[CustomFieldsInterface::MOLLIE_KEY])) {
            return;
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY])) {
            $this->setMollieOrderId((string) $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY]);
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYMENT_KEY])) {
            $this->setMolliePaymentId((string) $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYMENT_KEY]);
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::THIRD_PARTY_PAYMENT_KEY])) {
            $this->setThirdPartyPaymentId((string) $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::THIRD_PARTY_PAYMENT_KEY]);
        }

        if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::WEBHOOK_RECEIVED])) {
            $this->setWebhookReceived((int) $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::WEBHOOK_RECEIVED]);
        }
    }
}
