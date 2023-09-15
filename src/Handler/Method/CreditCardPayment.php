<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Mollie\Api\Types\PaymentMethod;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::CREDITCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Card';
    protected const FIELD_CREDIT_CARD_TOKEN = 'cardToken';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var bool
     */
    private $enableSingleClickPayment = false;

    public function __construct(
        LoggerInterface    $logger,
        ContainerInterface $container,
        CustomerService    $customerService
    ) {
        parent::__construct($logger, $container);
        $this->customerService = $customerService;
    }

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
        $cardToken = $customFields['mollie_payments']['credit_card_token'] ?? '';

        if (!empty($cardToken)) {
            $orderData['payment']['cardToken'] = $cardToken;
            $this->customerService->setCardToken($customer, '', $salesChannelContext);

            $isSaveCardToken = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL] ?? false;
            # change payment sequenceType to first if this is a single-click payment
            if ($this->enableSingleClickPayment && $isSaveCardToken) {
                $orderData['payment']['sequenceType'] = PaymentHandler::PAYMENT_SEQUENCE_TYPE_FIRST;
            }

            return $orderData;
        }

        # if single click payment is disabled, return orderData
        # if single click payment is enabled, we need to change the payment sequenceType and provide the mandateId
        if (!$this->enableSingleClickPayment) {
            return $orderData;
        }

        $mandateId = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_MANDATE_ID] ?? '';
        if (empty($mandateId)) {
            return $orderData;
        }

        # if mandateId is not empty, it means this is recurring payment
        $orderData['payment']['sequenceType'] = PaymentHandler::PAYMENT_SEQUENCE_TYPE_RECURRING;
        $orderData['payment']['mandateId'] = $mandateId;
        $this->customerService->setMandateId($customer, '', $salesChannelContext->getContext());

        return $orderData;
    }

    /**
     * @param bool $enableSingleClickPayment
     * @return void
     */
    public function setEnableSingleClickPayment(bool $enableSingleClickPayment): void
    {
        $this->enableSingleClickPayment = $enableSingleClickPayment;
    }
}
