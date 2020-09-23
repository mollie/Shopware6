<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::CREDITCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Credit card';
    protected const FIELD_CREDIT_CARD_TOKEN = 'cardToken';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        $customFields = $customer->getCustomFields();

        if (!array_key_exists(static::FIELD_CREDIT_CARD_TOKEN, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_CREDIT_CARD_TOKEN], [null, ''], true)) {
            $orderData[static::FIELD_PAYMENT][static::FIELD_CREDIT_CARD_TOKEN] = $customFields['mollie_payments']['credit_card_token'];
            $this->customerService->setCardToken($customer, '', $salesChannelContext->getContext());
        }

        return $orderData;
    }
}