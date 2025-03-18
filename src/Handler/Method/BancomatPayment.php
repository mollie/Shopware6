<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BancomatPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANCOMATPAY;
    public const PAYMENT_METHOD_DESCRIPTION = 'Bancomat Pay';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    /**
     * @param array<mixed> $orderData
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $orderCustomFields = new OrderAttributes($orderEntity);
        $orderData['billingAddress']['phone'] = $orderCustomFields->getBancomatPayPhoneNumber();

        return $orderData;
    }
}
