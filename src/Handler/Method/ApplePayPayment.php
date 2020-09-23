<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::APPLEPAY;
    public const PAYMENT_METHOD_DESCRIPTION = 'Apple Pay';
    protected const FIELD_APPLE_PAY_PAYMENT_TOKEN = 'applePayPaymentToken';


    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(static::FIELD_APPLE_PAY_PAYMENT_TOKEN, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_APPLE_PAY_PAYMENT_TOKEN], [null, ''], true)) {
            // Laten we voor nu even liggen
        }

        return $orderData;
    }
}