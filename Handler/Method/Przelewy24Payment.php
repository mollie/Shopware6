<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Przelewy24Payment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PRZELEWY24;
    public const PAYMENT_METHOD_DESCRIPTION = 'Przelewy24';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    /**
     * @param array               $orderData
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity      $customer
     * @param LocaleEntity        $locale
     *
     * @return array
     */
    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(self::FIELD_BILLING_EMAIL, $orderData[self::FIELD_PAYMENT]) || in_array($orderData[self::FIELD_PAYMENT][self::FIELD_BILLING_EMAIL], [null, ''], true)) {
            $orderData[self::FIELD_PAYMENT][self::FIELD_BILLING_EMAIL] = $customer->getEmail();
        }

        return $orderData;
    }
}