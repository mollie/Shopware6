<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class KbcPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::KBC;
    public const PAYMENT_METHOD_DESCRIPTION = 'KBC/CBC Payment Button';
    protected const FIELD_KBC_DESCRIPTION = 'description';
    protected const KBC_DESCRIPTION_LENGTH = 13;
    protected const FIELD_KBC_ISSUER = 'issuer';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(self::FIELD_KBC_DESCRIPTION, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_KBC_DESCRIPTION], [null, ''], true)) {
            $orderData[self::FIELD_PAYMENT][self::FIELD_KBC_DESCRIPTION] = substr($orderData[PaymentHandler::FIELD_ORDER_NUMBER], -self::KBC_DESCRIPTION_LENGTH);
        }

        if (!array_key_exists(self::FIELD_KBC_ISSUER, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_KBC_ISSUER], [null, ''], true)) {
            // ToDo: Pas zodra ingebouwd in frontent, gebeurt voorlopig niet
        }

        return $orderData;
    }
}