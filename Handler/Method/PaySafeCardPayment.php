<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaySafeCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PAYSAFECARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'paysafecard';
    protected const FIELD_PAY_SAFE_CARD_CUSTOMER_REFERENCE = 'customerReference';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(self::FIELD_PAY_SAFE_CARD_CUSTOMER_REFERENCE, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_PAY_SAFE_CARD_CUSTOMER_REFERENCE], [null, ''], true)) {
            $orderData[self::FIELD_PAYMENT][self::FIELD_PAY_SAFE_CARD_CUSTOMER_REFERENCE] = $customer->getCustomerNumber();
        }

        return $orderData;
    }
}