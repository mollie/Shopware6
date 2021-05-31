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

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        $customFields = $customer->getCustomFields() ?? [];

        $issuer = $customFields['mollie_payments']['preferred_ideal_issuer'] ?? '';

        if (empty($issuer)) {
            return $orderData;
        }

        if (!isset($orderData['payment']['issuer']) || empty($orderData['payment']['issuer'])) {
            $orderData['payment']['issuer'] = $issuer;
        }

        return $orderData;
    }
}
