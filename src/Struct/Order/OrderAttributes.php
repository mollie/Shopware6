<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Order;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
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
     * @var string
     */
    private $bankName;

    /**
     * @var string
     */
    private $bankAccount;

    /**
     * @var string
     */
    private $bankBic;

    /**
     * @var string
     */
    private $payPalExpressAuthenticateId;

    /**
     * @var string
     */
    private $bancomatPayPhoneNumber;

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
        $this->bankName = $this->getCustomFieldValue($order, 'bankName');
        $this->bankAccount = $this->getCustomFieldValue($order, 'bankAccount');
        $this->bankBic = $this->getCustomFieldValue($order, 'bankBic');
        $this->timezone = $this->getCustomFieldValue($order, 'timezone');
        $this->bancomatPayPhoneNumber = $this->getCustomFieldValue($order, 'bancomatPayPhoneNumber');
        $this->payPalExpressAuthenticateId = $this->getCustomFieldValue($order, CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID);
    }

    public function getMollieOrderId(): string
    {
        return (string) $this->mollieOrderId;
    }

    public function setMollieOrderId(?string $mollieOrderId): void
    {
        $this->mollieOrderId = $mollieOrderId;
    }

    public function getMolliePaymentId(): string
    {
        return (string) $this->molliePaymentId;
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

    public function getTransactionReturnUrl(): ?string
    {
        return $this->transactionReturnUrl;
    }

    public function setTransactionReturnUrl(?string $transactionReturnUrl): void
    {
        $this->transactionReturnUrl = $transactionReturnUrl;
    }

    public function setSubscriptionData(string $swSubscriptionId, string $mollieSubscriptionId): void
    {
        $this->swSubscriptionId = $swSubscriptionId;
        $this->mollieSubscriptionId = $mollieSubscriptionId;
    }

    public function getMolliePaymentUrl(): ?string
    {
        return $this->molliePaymentUrl;
    }

    public function setMolliePaymentUrl(?string $molliePaymentUrl): void
    {
        $this->molliePaymentUrl = $molliePaymentUrl;
    }

    public function getCreditCardNumber(): string
    {
        return $this->creditCardNumber;
    }

    public function setCreditCardNumber(string $creditCardNumber): void
    {
        $this->creditCardNumber = $creditCardNumber;
    }

    public function getCreditCardHolder(): string
    {
        return $this->creditCardHolder;
    }

    public function setCreditCardHolder(string $creditCardHolder): void
    {
        $this->creditCardHolder = $creditCardHolder;
    }

    public function getCreditCardAudience(): string
    {
        return $this->creditCardAudience;
    }

    public function setCreditCardAudience(string $creditCardAudience): void
    {
        $this->creditCardAudience = $creditCardAudience;
    }

    public function getCreditCardLabel(): string
    {
        return $this->creditCardLabel;
    }

    public function setCreditCardLabel(string $creditCardLabel): void
    {
        $this->creditCardLabel = $creditCardLabel;
    }

    public function getCreditCardCountryCode(): string
    {
        return $this->creditCardCountryCode;
    }

    public function setCreditCardCountryCode(string $creditCardCountryCode): void
    {
        $this->creditCardCountryCode = $creditCardCountryCode;
    }

    public function getCreditCardSecurity(): string
    {
        return $this->creditCardSecurity;
    }

    public function setCreditCardSecurity(string $creditCardSecurity): void
    {
        $this->creditCardSecurity = $creditCardSecurity;
    }

    public function getCreditCardFeeRegion(): string
    {
        return $this->creditCardFeeRegion;
    }

    public function setCreditCardFeeRegion(string $creditCardFeeRegion): void
    {
        $this->creditCardFeeRegion = $creditCardFeeRegion;
    }

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function setBankName(string $bankName): void
    {
        $this->bankName = $bankName;
    }

    public function getBankAccount(): string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(string $bankAccount): void
    {
        $this->bankAccount = $bankAccount;
    }

    public function getBankBic(): string
    {
        return $this->bankBic;
    }

    public function setBankBic(string $bankBic): void
    {
        $this->bankBic = $bankBic;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return void
     */
    public function setCreditCardDetails(?\stdClass $details)
    {
        if (! empty($details->cardNumber)) {
            $this->creditCardNumber = $details->cardNumber;
        }
        if (! empty($details->cardHolder)) {
            $this->creditCardHolder = $details->cardHolder;
        }
        if (! empty($details->cardAudience)) {
            $this->creditCardAudience = $details->cardAudience;
        }
        if (! empty($details->cardLabel)) {
            $this->creditCardLabel = $details->cardLabel;
        }
        if (! empty($details->cardCountryCode)) {
            $this->creditCardCountryCode = $details->cardCountryCode;
        }
        if (! empty($details->cardSecurity)) {
            $this->creditCardSecurity = $details->cardSecurity;
        }
        if (! empty($details->feeRegion)) {
            $this->creditCardFeeRegion = $details->feeRegion;
        }
    }

    /**
     * @return void
     */
    public function setBankTransferDetails(?\stdClass $details)
    {
        if (! empty($details->bankName)) {
            $this->bankName = $details->bankName;
        }
        if (! empty($details->bankAccount)) {
            $this->bankAccount = $details->bankAccount;
        }
        if (! empty($details->bankBic)) {
            $this->bankBic = $details->bankBic;
        }
    }

    public function getPayPalExpressAuthenticateId(): string
    {
        return $this->payPalExpressAuthenticateId;
    }

    public function setPayPalExpressAuthenticateId(string $payPalExpressAuthenticateId): void
    {
        $this->payPalExpressAuthenticateId = $payPalExpressAuthenticateId;
    }

    public function getBancomatPayPhoneNumber(): string
    {
        return $this->bancomatPayPhoneNumber;
    }

    public function setBancomatPayPhoneNumber(string $bancomatPayPhoneNumber): void
    {
        $this->bancomatPayPhoneNumber = $bancomatPayPhoneNumber;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $mollieData = [];

        // lets save some space and only store
        // what is existing
        if ((string) $this->mollieOrderId !== '') {
            $mollieData['order_id'] = $this->mollieOrderId;
        }

        if ((string) $this->molliePaymentId !== '') {
            $mollieData['payment_id'] = $this->molliePaymentId;
        }

        if ((string) $this->swSubscriptionId !== '') {
            $mollieData['swSubscriptionId'] = $this->swSubscriptionId;
        }

        if ((string) $this->mollieSubscriptionId !== '') {
            $mollieData['mollieSubscriptionId'] = $this->mollieSubscriptionId;
        }

        if ((string) $this->mollieOrderId !== '') {
            $mollieData['third_party_payment_id'] = $this->thirdPartyPaymentId;
        }

        // used by the Mollie Failure-Mode within this plugin
        if ((string) $this->transactionReturnUrl !== '') {
            $mollieData['transactionReturnUrl'] = $this->transactionReturnUrl;
        }

        // used for the API to read the checkout-URL
        // within 3rd party systems
        if ((string) $this->molliePaymentUrl !== '') {
            $mollieData['molliePaymentUrl'] = $this->molliePaymentUrl;
        }

        if ((string) $this->creditCardNumber !== '') {
            $mollieData['creditCardNumber'] = $this->creditCardNumber;
        }

        if ((string) $this->creditCardHolder !== '') {
            $mollieData['creditCardHolder'] = $this->creditCardHolder;
        }

        if ((string) $this->creditCardAudience !== '') {
            $mollieData['creditCardAudience'] = $this->creditCardAudience;
        }

        if ((string) $this->creditCardLabel !== '') {
            $mollieData['creditCardLabel'] = $this->creditCardLabel;
        }

        if ((string) $this->creditCardCountryCode !== '') {
            $mollieData['creditCardCountryCode'] = $this->creditCardCountryCode;
        }

        if ((string) $this->creditCardSecurity !== '') {
            $mollieData['creditCardSecurity'] = $this->creditCardSecurity;
        }

        if ((string) $this->creditCardFeeRegion !== '') {
            $mollieData['creditCardFeeRegion'] = $this->creditCardFeeRegion;
        }

        if ((string) $this->timezone !== '') {
            $mollieData['timezone'] = $this->timezone;
        }

        if ((string) $this->bankName !== '') {
            $mollieData['bankName'] = $this->bankName;
        }

        if ((string) $this->bankAccount !== '') {
            $mollieData['bankAccount'] = $this->bankAccount;
        }

        if ((string) $this->bankBic !== '') {
            $mollieData['bankBic'] = $this->bankBic;
        }

        if ($this->bancomatPayPhoneNumber !== '') {
            $mollieData['bancomatPayPhoneNumber'] = $this->bancomatPayPhoneNumber;
        }
        if ((string) $this->payPalExpressAuthenticateId !== '') {
            $mollieData[CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] = $this->payPalExpressAuthenticateId;
        }

        return [
            CustomFieldsInterface::MOLLIE_KEY => $mollieData,
        ];
    }

    public function isTypeSubscription(): bool
    {
        // if we already have a mollie subscription ID
        // then we KNOW it's a subscription
        if (! empty($this->mollieSubscriptionId)) {
            return true;
        }

        // also a shopware subscription id reference, means we have one
        if (! empty($this->swSubscriptionId)) {
            return true;
        }

        // otherwise, verify if we have subscription items
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

    private function getCustomFieldValue(OrderEntity $order, string $keyName): string
    {
        $foundValue = '';

        $customFields = $order->getCustomFields();

        // check if we have a mollie entry
        if ($customFields !== null && array_key_exists(CustomFieldsInterface::MOLLIE_KEY, $customFields)) {
            // load the mollie entry
            $mollieData = $customFields[CustomFieldsInterface::MOLLIE_KEY];
            // assign our value if we have it
            $foundValue = (array_key_exists($keyName, $mollieData)) ? (string) $mollieData[$keyName] : '';
        }

        return $foundValue;
    }
}
