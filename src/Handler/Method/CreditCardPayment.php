<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Mollie\Api\Types\PaymentMethod;
use Mollie\Shopware\Component\Payment\FinalizeAction;
use Mollie\Shopware\Component\Payment\PayAction;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::CREDITCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Card';
    protected const FIELD_CREDIT_CARD_TOKEN = 'cardToken';

    protected string $paymentMethod = self::PAYMENT_METHOD_NAME;

    private CustomerService $customerService;

    private bool $enableSingleClickPayment = false;

    public function __construct(PayAction $payAction, FinalizeAction $finalizeAction, CustomerService $customerService)
    {
        parent::__construct($payAction, $finalizeAction);
        $this->customerService = $customerService;
    }

    /**
     * @param array<mixed> $orderData
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $customFields = $customer->getCustomFields() ?? [];
        $cardToken = $customFields[CustomFieldsInterface::MOLLIE_KEY]['credit_card_token'] ?? '';

        if (! empty($cardToken)) {
            $orderData['payment']['cardToken'] = $cardToken;
            $this->customerService->setCardToken($customer, '', $salesChannelContext);

            $isSaveCardToken = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomerService::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL] ?? false;
            // change payment sequenceType to first if this is a single-click payment
            if ($this->enableSingleClickPayment && $isSaveCardToken) {
                $orderData['payment']['sequenceType'] = PaymentHandler::PAYMENT_SEQUENCE_TYPE_FIRST;
            }

            return $orderData;
        }

        // if single click payment is disabled, return orderData
        // if single click payment is enabled, we need to change the payment sequenceType and provide the mandateId
        if (! $this->enableSingleClickPayment) {
            return $orderData;
        }

        $mandateId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomerService::CUSTOM_FIELDS_KEY_MANDATE_ID] ?? '';
        if (empty($mandateId)) {
            return $orderData;
        }

        // if mandateId is not empty, it means this is recurring payment
        $orderData['payment']['sequenceType'] = PaymentHandler::PAYMENT_SEQUENCE_TYPE_RECURRING;
        $orderData['payment']['mandateId'] = $mandateId;
        $this->customerService->setMandateId($customer, '', $salesChannelContext->getContext());

        return $orderData;
    }

    public function setEnableSingleClickPayment(bool $enableSingleClickPayment): void
    {
        $this->enableSingleClickPayment = $enableSingleClickPayment;
    }
}
