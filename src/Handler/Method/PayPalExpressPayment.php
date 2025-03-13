<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalExpressPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = 'paypalexpress';

    public const PAYMENT_METHOD_DESCRIPTION = 'PayPal Express';

    /** @var string */
    protected $paymentMethod = PaymentMethod::PAYPAL;

    /**
     * @param array<mixed> $orderData
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $orderData['authenticationId'] = $orderEntity->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID] ?? null;

        return $orderData;
    }
}
