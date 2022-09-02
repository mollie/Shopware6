<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class iDealPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::IDEAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'iDEAL';
    protected const FIELD_IDEAL_ISSUER = 'issuer';

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
        $customFields = $customer->getCustomFields() ?? [];

        $issuer = $customFields['mollie_payments']['preferred_ideal_issuer'] ?? '';
        $paymentIssuer = $orderData['payment']['issuer'] ?? '';

        if (empty($issuer)) {
            return $orderData;
        }

        if (empty($paymentIssuer)) {
            $orderData['payment']['issuer'] = $issuer;
        }

        return $orderData;
    }
}
