<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class iDealPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::IDEAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'iDEAL';
    protected const FIELD_IDEAL_ISSUER = 'issuer';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(static::FIELD_IDEAL_ISSUER, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_IDEAL_ISSUER], [null, ''], true)) {
            //ToDo: Dropdown met banken in checkout, daarna deze vullen
        }

        return $orderData;
    }
}