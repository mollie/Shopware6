<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BanContactPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANCONTACT;
    public const PAYMENT_METHOD_DESCRIPTION = 'Bancontact';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        return $orderData;
    }
}
