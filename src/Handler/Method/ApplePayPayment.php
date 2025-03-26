<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::APPLEPAY;
    public const PAYMENT_METHOD_DESCRIPTION = 'Apple Pay';


    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;

    /** @var string */
    private $token;

    /**
     * @param array<mixed> $orderData
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        if (! empty($this->token)) {
            $orderData['payment']['applePayPaymentToken'] = $this->token;
        }

        return $orderData;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }
}
