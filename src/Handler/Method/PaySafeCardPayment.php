<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaySafeCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PAYSAFECARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'paysafecard';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer
    ): array
    {
        $reference = $orderData['payment']['customerReference'] ?? '';

        if (empty($reference)) {
            $orderData['payment']['customerReference'] = $customer->getCustomerNumber();
        }

        return $orderData;
    }
}
