<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Order;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderAttributes
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
     * @var string
     */
    private $swSubscriptionId;

    /**
     * @var string
     */
    private $mollieSubscriptionId;

    /**
     * @var null|string
     */
    private $thirdPartyPaymentId;

    /**
     * @var null|string
     */
    private $transactionReturnUrl;

    /**
     * @var null|string
     */
    private $molliePaymentUrl;

    /**
     * @var OrderEntity
     */
    private $order;


    /**
     * @param OrderEntity $order
     */
    public function __construct(OrderEntity $order)
    {
        $this->order = $order;
        $this->mollieOrderId = $this->getCustomFieldValue($order, 'order_id');
        $this->molliePaymentId = $this->getCustomFieldValue($order, 'payment_id');
        $this->swSubscriptionId = $this->getCustomFieldValue($order, 'swSubscriptionId');
        $this->mollieSubscriptionId = $this->getCustomFieldValue($order, 'mollieSubscriptionId');
        $this->thirdPartyPaymentId = $this->getCustomFieldValue($order, 'third_party_payment_id');
        $this->transactionReturnUrl = $this->getCustomFieldValue($order, 'transactionReturnUrl');
        $this->molliePaymentUrl = $this->getCustomFieldValue($order, 'molliePaymentUrl');
    }

    /**
     * @return string
     */
    public function getMollieOrderId(): string
    {
        return (string)$this->mollieOrderId;
    }

    /**
     * @param null|string $mollieOrderId
     */
    public function setMollieOrderId(?string $mollieOrderId): void
    {
        $this->mollieOrderId = $mollieOrderId;
    }

    /**
     * @return string
     */
    public function getMolliePaymentId(): string
    {
        return (string)$this->molliePaymentId;
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
     * @return null|string
     */
    public function getTransactionReturnUrl(): ?string
    {
        return $this->transactionReturnUrl;
    }

    /**
     * @param null|string $transactionReturnUrl
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
     * @return null|string
     */
    public function getMolliePaymentUrl(): ?string
    {
        return $this->molliePaymentUrl;
    }

    /**
     * @param null|string $molliePaymentUrl
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
        if ((string)$this->mollieOrderId !== '') {
            $mollieData['order_id'] = $this->mollieOrderId;
        }

        if ((string)$this->molliePaymentId !== '') {
            $mollieData['payment_id'] = $this->molliePaymentId;
        }

        if ((string)$this->swSubscriptionId !== '') {
            $mollieData['swSubscriptionId'] = $this->swSubscriptionId;
        }

        if ((string)$this->mollieSubscriptionId !== '') {
            $mollieData['mollieSubscriptionId'] = $this->mollieSubscriptionId;
        }

        if ((string)$this->mollieOrderId !== '') {
            $mollieData['third_party_payment_id'] = $this->thirdPartyPaymentId;
        }

        if ((string)$this->transactionReturnUrl !== '') {
            $mollieData['transactionReturnUrl'] = $this->transactionReturnUrl;
        }

        if ((string)$this->molliePaymentUrl !== '') {
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
        # if we already have a mollie subscription ID
        # then we KNOW it's a subscription
        if (!empty($this->mollieSubscriptionId)) {
            return true;
        }

        # otherwise, verify if we have subscription items
        if ($this->order->getLineItems() instanceof OrderLineItemCollection) {
            foreach ($this->order->getLineItems() as $lineItem) {
                $attribute = new OrderLineItemEntityAttributes($lineItem);
                if ($attribute->isSubscriptionProduct()) {
                    return true;
                }
            }
        }

        return false;
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
