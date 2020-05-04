<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PAYPAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'PayPal';
    protected const FIELD_PAYPAL_DESCRIPTION = 'description';
    protected const FIELD_PAYPAL_DIGITAL_GOODS = 'digitalGoods';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(self::FIELD_PAYPAL_DESCRIPTION, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_PAYPAL_DESCRIPTION], [null, ''], true)) {
            $orderData[self::FIELD_PAYMENT][self::FIELD_PAYPAL_DESCRIPTION] = sprintf('Order %s', $orderData[PaymentHandler::FIELD_ORDER_NUMBER]);
        }

        if (!array_key_exists(self::FIELD_PAYPAL_DIGITAL_GOODS, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_PAYPAL_DIGITAL_GOODS], [null, ''], true)) {
            // ToDo: Digital downloads zitten nog niet in Shopware 6
        }

        return $orderData;
    }
}