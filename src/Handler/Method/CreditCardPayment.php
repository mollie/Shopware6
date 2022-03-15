<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Mollie\Api\Types\PaymentMethod;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::CREDITCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Credit card';
    protected const FIELD_CREDIT_CARD_TOKEN = 'cardToken';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
    /**
     * @var CustomerService
     */
    private $customerService;

    public function __construct(
        LoggerInterface $logger,
        ContainerInterface         $container,
        CustomerService $customerService
    )
    {
        parent::__construct($logger, $container);
        $this->customerService = $customerService;
    }

    public function processPaymentMethodSpecificParameters(
        array               $orderData,
        OrderEntity         $orderEntity,
        SalesChannelContext $salesChannelContext,
        CustomerEntity      $customer
    ): array
    {
        $customFields = $customer->getCustomFields() ?? [];
        $cardToken = $customFields['mollie_payments']['credit_card_token'] ?? '';

        if (empty($cardToken)) {
            return $orderData;
        }

        $orderData['payment']['cardToken'] = $cardToken;
        $this->customerService->setCardToken($customer, '', $salesChannelContext->getContext());

        return $orderData;
    }
}
