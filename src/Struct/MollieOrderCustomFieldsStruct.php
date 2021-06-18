<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

class MollieOrderCustomFieldsStruct
{
    /** @var string|null */
    private $mollieOrderId;

    /** @var string|null */
    private $transactionReturnUrl;

    /** @var string|null */
    private $molliePaymentUrl;

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

    /**
     * @return string|null
     */
    public function getTransactionReturnUrl(): ?string
    {
        return $this->transactionReturnUrl;
    }

    /**
     * @param string|null $transactionReturnUrl
     */
    public function setTransactionReturnUrl(?string $transactionReturnUrl): void
    {
        $this->transactionReturnUrl = $transactionReturnUrl;
    }

    /**
     * @return string|null
     */
    public function getMolliePaymentUrl(): ?string
    {
        return $this->molliePaymentUrl;
    }

    /**
     * @param string|null $molliePaymentUrl
     */
    public function setMolliePaymentUrl(?string $molliePaymentUrl): void
    {
        $this->molliePaymentUrl = $molliePaymentUrl;
    }

    /**
     * @return array<string,mixed>
     */
    public function getMollieCustomFields(): array
    {
        if (empty($this->mollieOrderId)) {
            return ['mollie_payments' => []];
        }

        return [
            'mollie_payments' => [
                'order_id' => $this->mollieOrderId,
                'transactionReturnUrl' => (string)$this->transactionReturnUrl,
                'molliePaymentUrl' => (string)$this->molliePaymentUrl
            ]
        ];
    }

    /**
     * @param array<string,mixed> $customFields
     */
    private function setCustomFields(array $customFields): void
    {
        if (!isset($customFields['mollie_payments'])) {
            return;
        }

        if (isset($customFields['mollie_payments']['order_id'])) {
            $this->setMollieOrderId((string)$customFields['mollie_payments']['order_id']);
        }

        if (isset($customFields['mollie_payments']['transactionReturnUrl'])) {
            $this->setTransactionReturnUrl((string)$customFields['mollie_payments']['transactionReturnUrl']);
        }

        if (isset($customFields['mollie_payments']['molliePaymentUrl'])) {
            $this->setMolliePaymentUrl((string)$customFields['mollie_payments']['molliePaymentUrl']);
        }
    }
}
