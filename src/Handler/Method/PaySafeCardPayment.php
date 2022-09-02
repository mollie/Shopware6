<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaySafeCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PAYSAFECARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'paysafecard';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;


    /**
     * @param array<mixed> $orderData
     * @param OrderEntity $orderEntity
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $reference = $orderData['payment']['customerReference'] ?? '';

        if (empty($reference)) {
            $orderData['payment']['customerReference'] = $customer->getCustomerNumber();
        }

        return $orderData;
    }
}
