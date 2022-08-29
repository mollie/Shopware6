<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
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


    /**
     * @param array<mixed> $orderData
     * @param OrderEntity $orderEntity
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        return $orderData;

        // @todo find error and fix it
//        if (!array_key_exists(static::FIELD_PAYPAL_DESCRIPTION, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_PAYPAL_DESCRIPTION], [null, ''], true)) {
//            $orderData[static::FIELD_PAYMENT][static::FIELD_PAYPAL_DESCRIPTION] = sprintf('Order %s', $orderData[PaymentHandler::FIELD_ORDER_NUMBER]);
//        }
//
//        if (!array_key_exists(static::FIELD_PAYPAL_DIGITAL_GOODS, $orderData[static::FIELD_PAYMENT]) || in_array($orderData[static::FIELD_PAYMENT][static::FIELD_PAYPAL_DIGITAL_GOODS], [null, ''], true)) {
//            // ToDo: Digital downloads zitten nog niet in Shopware 6
//        }
//
//        return $orderData;
    }
}
