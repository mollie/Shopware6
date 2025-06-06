<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class GiftCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::GIFTCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Gift cards';
    protected const FIELD_GIFT_CARD_ISSUER = 'issuer';
    protected const FIELD_GIFT_CARD_VOUCHER_NUMBER = 'voucherNumber';
    protected const FIELD_GIFT_CARD_VOUCHER_PIN = 'voucherPin';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;

    /**
     * @param array<mixed> $orderData
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        return $orderData;
    }
}
