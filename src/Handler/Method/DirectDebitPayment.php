<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DirectDebitPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::DIRECTDEBIT;
    public const PAYMENT_METHOD_DESCRIPTION = 'SEPA Direct Debit';

    protected const FIELD_SEPA_DIRECT_DEBIT_CONSUMER_NAME = 'consumerName';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        if (!array_key_exists(static::FIELD_SEPA_DIRECT_DEBIT_CONSUMER_NAME, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_SEPA_DIRECT_DEBIT_CONSUMER_NAME], [null, ''], true)) {
            $orderData[static::FIELD_PAYMENT][self::FIELD_SEPA_DIRECT_DEBIT_CONSUMER_NAME] = sprintf('%s %s', $customer->getFirstName(), $customer->getLastName());
        }

        return $orderData;
    }
}