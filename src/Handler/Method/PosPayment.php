<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PosPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::POINT_OF_SALE;
    public const PAYMENT_METHOD_DESCRIPTION = 'POS Terminal';

    /**
     * @var string
     */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    /**
     * @var string
     */
    private $selectedTerminalId;


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

        $this->selectedTerminalId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_POS_TERMINAL] ?? '';

        return $orderData;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->selectedTerminalId;
    }
}
