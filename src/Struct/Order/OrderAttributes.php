<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Order;


use Shopware\Core\Checkout\Order\OrderEntity;


class OrderAttributes
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
     * @var string
     */
    private $swSubscriptionId;

    /**
     * @var string
     */
    private $mollieSubscriptionId;

    /**
     * @var string|null
     */
    private $thirdPartyPaymentId;

    /**
     * @var string|null
     */
    private $transactionReturnUrl;

    /**
     * @var string|null
     */
    private $molliePaymentUrl;


    /**
     * @param OrderEntity $order
     */
    public function __construct(OrderEntity $order)
    {
        $this->mollieOrderId = $this->getCustomFieldValue($order, 'order_id');
        $this->molliePaymentId = $this->getCustomFieldValue($order, 'payment_id');
        $this->swSubscriptionId = $this->getCustomFieldValue($order, 'swSubscriptionId');
        $this->mollieSubscriptionId = $this->getCustomFieldValue($order, 'mollieSubscriptionId');
        $this->thirdPartyPaymentId = $this->getCustomFieldValue($order, 'third_party_payment_id');
        $this->transactionReturnUrl = $this->getCustomFieldValue($order, 'transactionReturnUrl');
        $this->molliePaymentUrl = $this->getCustomFieldValue($order, 'molliePaymentUrl');
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
     * @param string $swSubscriptionId
     * @param string $mollieSubscriptionId
     */
    public function setSubscriptionData(string $swSubscriptionId, string $mollieSubscriptionId): void
    {
        $this->swSubscriptionId = $swSubscriptionId;
        $this->mollieSubscriptionId = $mollieSubscriptionId;
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
    public function toArray(): array
    {
        $mollieData = [];

        # lets save some space and only store
        # what is existing
        if ($this->mollieOrderId !== null) {
            $mollieData['order_id'] = $this->mollieOrderId;
        }

        if ($this->molliePaymentId !== null) {
            $mollieData['payment_id'] = $this->molliePaymentId;
        }

        if ($this->mollieSubscriptionId !== null) {
            $mollieData['swSubscriptionId'] = $this->swSubscriptionId;
        }

        if ($this->mollieSubscriptionId !== null) {
            $mollieData['mollieSubscriptionId'] = $this->mollieSubscriptionId;
        }

        if ($this->mollieOrderId !== null) {
            $mollieData['third_party_payment_id'] = $this->thirdPartyPaymentId;
        }

        if ($this->transactionReturnUrl !== null) {
            $mollieData['transactionReturnUrl'] = $this->transactionReturnUrl;
        }

        if ($this->molliePaymentUrl !== null) {
            $mollieData['molliePaymentUrl'] = $this->molliePaymentUrl;
        }

        return [
            'mollie_payments' => $mollieData,
        ];
    }

    /**
     * @return bool
     */
    public function isTypeSubscription(): bool
    {
        return (!empty($this->mollieSubscriptionId));
    }

    /**
     * @param OrderEntity $order
     * @param string $keyName
     * @return string
     */
    private function getCustomFieldValue(OrderEntity $order, string $keyName): string
    {
        $foundValue = '';

        $customFields = $order->getCustomFields();

        # check if we have a mollie entry
        if ($customFields !== null && array_key_exists('mollie_payments', $customFields)) {
            # load the mollie entry
            $mollieData = $customFields['mollie_payments'];
            # assign our value if we have it
            $foundValue = (array_key_exists($keyName, $mollieData)) ? (string)$mollieData[$keyName] : '';
        }

        return $foundValue;
    }
}
