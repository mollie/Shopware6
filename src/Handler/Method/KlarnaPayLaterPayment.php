<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class KlarnaPayLaterPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::KLARNA_PAY_LATER;
    public const PAYMENT_METHOD_DESCRIPTION = 'Pay later.';

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
