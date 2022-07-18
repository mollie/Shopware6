<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Order;

use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use stdClass;


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
     * @var string|null
     */
    private $creditCardNumber;

    /**
     * @var string|null
     */
    private $creditCardHolder;

    /**
     * @var string|null
     */
    private $creditCardAudience;

    /**
     * @var string|null
     */
    private $creditCardLabel;

    /**
     * @var string|null
     */
    private $creditCardCountryCode;

    /**
     * @var string|null
     */
    private $creditCardSecurity;

    /**
     * @var string|null
     */
    private $creditCardFeeRegion;


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
        $this->creditCardNumber = $this->getCustomFieldValue($order, 'creditCardNumber');
        $this->creditCardHolder = $this->getCustomFieldValue($order, 'creditCardHolder');
        $this->creditCardAudience = $this->getCustomFieldValue($order, 'creditCardAudience');
        $this->creditCardLabel = $this->getCustomFieldValue($order, 'creditCardLabel');
        $this->creditCardCountryCode = $this->getCustomFieldValue($order, 'creditCardCountryCode');
        $this->creditCardSecurity = $this->getCustomFieldValue($order, 'creditCardSecurity');
        $this->creditCardFeeRegion = $this->getCustomFieldValue($order, 'creditCardFeeRegion');

    }

    /**
     * @return string
     */
    public function getMollieOrderId(): string
    {
        return (string)$this->mollieOrderId;
    }

    /**
     * @param string|null $mollieOrderId
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
     * @return string|null
     */
    public function getCreditCardNumber(): ?string
    {
        return $this->creditCardNumber;
    }

    /**
     * @param string|null $creditCardNumber
     */
    public function setCreditCardNumber(?string $creditCardNumber): void
    {
        $this->creditCardNumber = $creditCardNumber;
    }

    /**
     * @return string|null
     */
    public function getCreditCardHolder(): ?string
    {
        return $this->creditCardHolder;
    }

    /**
     * @param string|null $creditCardHolder
     */
    public function setCreditCardHolder(?string $creditCardHolder): void
    {
        $this->creditCardHolder = $creditCardHolder;
    }

    /**
     * @return string|null
     */
    public function getCreditCardAudience(): ?string
    {
        return $this->creditCardAudience;
    }

    /**
     * @param string|null $creditCardAudience
     */
    public function setCreditCardAudience(?string $creditCardAudience): void
    {
        $this->creditCardAudience = $creditCardAudience;
    }

    /**
     * @return string|null
     */
    public function getCreditCardLabel(): ?string
    {
        return $this->creditCardLabel;
    }

    /**
     * @param string|null $creditCardLabel
     */
    public function setCreditCardLabel(?string $creditCardLabel): void
    {
        $this->creditCardLabel = $creditCardLabel;
    }

    /**
     * @return string|null
     */
    public function getCreditCardCountryCode(): ?string
    {
        return $this->creditCardCountryCode;
    }

    /**
     * @param string|null $creditCardCountryCode
     */
    public function setCreditCardCountryCode(?string $creditCardCountryCode): void
    {
        $this->creditCardCountryCode = $creditCardCountryCode;
    }

    /**
     * @return string|null
     */
    public function getCreditCardSecurity(): ?string
    {
        return $this->creditCardSecurity;
    }

    /**
     * @param string|null $creditCardSecurity
     */
    public function setCreditCardSecurity(?string $creditCardSecurity): void
    {
        $this->creditCardSecurity = $creditCardSecurity;
    }

    /**
     * @return string|null
     */
    public function getCreditCardFeeRegion(): ?string
    {
        return $this->creditCardFeeRegion;
    }

    /**
     * @param string|null $creditCardFeeRegion
     */
    public function setCreditCardFeeRegion(?string $creditCardFeeRegion): void
    {
        $this->creditCardFeeRegion = $creditCardFeeRegion;
    }

    /**
     * @param stdClass|null $details
     * @return void
     */
    public function setCreditCardDetails(?stdClass $details)
    {
        if (!empty($details->cardNumber)) {
            $this->creditCardNumber = $details->cardNumber;
        }
        if (!empty($details->cardHolder)) {
            $this->creditCardHolder = $details->cardHolder;
        }
        if (!empty($details->cardAudience)) {
            $this->creditCardAudience = $details->cardAudience;
        }
        if (!empty($details->cardLabel)) {
            $this->creditCardLabel = $details->cardLabel;
        }
        if (!empty($details->cardCountryCode)) {
            $this->creditCardCountryCode = $details->cardCountryCode;
        }
        if (!empty($details->cardSecurity)) {
            $this->creditCardSecurity = $details->cardSecurity;
        }
        if (!empty($details->feeRegion)) {
            $this->creditCardFeeRegion = $details->feeRegion;
        }
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

        if ((string)$this->creditCardNumber !== '') {
            $mollieData['creditCardNumber'] = $this->creditCardNumber;
        }

        if ((string)$this->creditCardHolder !== '') {
            $mollieData['creditCardHolder'] = $this->creditCardHolder;
        }

        if ((string)$this->creditCardAudience !== '') {
            $mollieData['creditCardAudience'] = $this->creditCardAudience;
        }

        if ((string)$this->creditCardLabel !== '') {
            $mollieData['creditCardLabel'] = $this->creditCardLabel;
        }

        if ((string)$this->creditCardCountryCode !== '') {
            $mollieData['creditCardCountryCode'] = $this->creditCardCountryCode;
        }

        if ((string)$this->creditCardSecurity !== '') {
            $mollieData['creditCardSecurity'] = $this->creditCardSecurity;
        }

        if ((string)$this->creditCardFeeRegion !== '') {
            $mollieData['creditCardFeeRegion'] = $this->creditCardFeeRegion;
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
