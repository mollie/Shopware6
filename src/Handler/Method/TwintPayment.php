<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TwintPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'twint';
    public const PAYMENT_METHOD_DESCRIPTION = 'TWINT';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

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
