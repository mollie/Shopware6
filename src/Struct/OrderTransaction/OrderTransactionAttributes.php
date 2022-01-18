<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\OrderTransaction;


class OrderTransactionAttributes
{

    /**
     * @var string|null
     */
    private $mollieOrderId;

    /**
     * @var string|null
     */
    private $molliePaymentId;

    /**
     * @var string|null
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
    public function getMolliePaymentId(): ?string
    {
        return $this->molliePaymentId;
    }

    /**
     * @param string|null $molliePaymentId
     */
    public function setMolliePaymentId(?string $molliePaymentId): void
    {
        $this->molliePaymentId = $molliePaymentId;
    }

    /**
     * @return string|null
     */
    public function getThirdPartyPaymentId(): ?string
    {
        return $this->thirdPartyPaymentId;
    }

    /**
     * @param string|null $thirdPartyPaymentId
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
            'order_id' => $this->mollieOrderId,
            'payment_id' => $this->molliePaymentId,
            'third_party_payment_id' => $this->thirdPartyPaymentId,
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

        if (isset($customFields['mollie_payments']['payment_id'])) {
            $this->setMolliePaymentId((string)$customFields['mollie_payments']['payment_id']);
        }

        if (isset($customFields['mollie_payments']['third_party_payment_id'])) {
            $this->setThirdPartyPaymentId((string)$customFields['mollie_payments']['third_party_payment_id']);
        }
    }
}
