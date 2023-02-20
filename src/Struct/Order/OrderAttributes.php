<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Order;

use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use stdClass;

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
     * @var string
     */
    private $creditCardNumber;

    /**
     * @var string
     */
    private $creditCardHolder;

    /**
     * @var string
     */
    private $creditCardAudience;

    /**
     * @var string
     */
    private $creditCardLabel;

    /**
     * @var string
     */
    private $creditCardCountryCode;

    /**
     * @var string
     */
    private $creditCardSecurity;

    /**
     * @var string
     */
    private $creditCardFeeRegion;

    /**
     * @var string
     */
    private $timezone;

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
        $this->timezone = $this->getCustomFieldValue($order, 'timezone');
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
     * @return string
     */
    public function getCreditCardNumber(): string
    {
        return $this->creditCardNumber;
    }

    /**
     * @param string $creditCardNumber
     */
    public function setCreditCardNumber(string $creditCardNumber): void
    {
        $this->creditCardNumber = $creditCardNumber;
    }

    /**
     * @return string
     */
    public function getCreditCardHolder(): string
    {
        return $this->creditCardHolder;
    }

    /**
     * @param string $creditCardHolder
     */
    public function setCreditCardHolder(string $creditCardHolder): void
    {
        $this->creditCardHolder = $creditCardHolder;
    }

    /**
     * @return string
     */
    public function getCreditCardAudience(): string
    {
        return $this->creditCardAudience;
    }

    /**
     * @param string $creditCardAudience
     */
    public function setCreditCardAudience(string $creditCardAudience): void
    {
        $this->creditCardAudience = $creditCardAudience;
    }

    /**
     * @return string
     */
    public function getCreditCardLabel(): string
    {
        return $this->creditCardLabel;
    }

    /**
     * @param string $creditCardLabel
     */
    public function setCreditCardLabel(string $creditCardLabel): void
    {
        $this->creditCardLabel = $creditCardLabel;
    }

    /**
     * @return string
     */
    public function getCreditCardCountryCode(): string
    {
        return $this->creditCardCountryCode;
    }

    /**
     * @param string $creditCardCountryCode
     */
    public function setCreditCardCountryCode(string $creditCardCountryCode): void
    {
        $this->creditCardCountryCode = $creditCardCountryCode;
    }

    /**
     * @return string
     */
    public function getCreditCardSecurity(): string
    {
        return $this->creditCardSecurity;
    }

    /**
     * @param string $creditCardSecurity
     */
    public function setCreditCardSecurity(string $creditCardSecurity): void
    {
        $this->creditCardSecurity = $creditCardSecurity;
    }

    /**
     * @return string
     */
    public function getCreditCardFeeRegion(): string
    {
        return $this->creditCardFeeRegion;
    }

    /**
     * @param string $creditCardFeeRegion
     */
    public function setCreditCardFeeRegion(string $creditCardFeeRegion): void
    {
        $this->creditCardFeeRegion = $creditCardFeeRegion;
    }

    /**
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * @param string $timezone
     */
    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @param null|stdClass $details
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

        # used by the Mollie Failure-Mode within this plugin
        if ((string)$this->transactionReturnUrl !== '') {
            $mollieData['transactionReturnUrl'] = $this->transactionReturnUrl;
        }

        # used for the API to read the checkout-URL
        # within 3rd party systems
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

        if ((string)$this->timezone !== '') {
            $mollieData['timezone'] = $this->timezone;
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

        # also a shopware subscription id reference, means we have one
        if (!empty($this->swSubscriptionId)) {
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
