<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreditCardPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::CREDITCARD;
    public const PAYMENT_METHOD_DESCRIPTION = 'Credit card';
    protected const FIELD_CREDIT_CARD_TOKEN = 'cardToken';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;
    /**
     * @var SettingsService
     */
    private $settingsService;

    public function __construct(
        LoggerService $logger,
        MolliePaymentDoPay $payFacade,
        MolliePaymentFinalize $finalizeFacade,
        TransactionTransitionServiceInterface $transactionTransitionService,
        SettingsService $settingsService
    )
    {
        parent::__construct($logger, $payFacade, $finalizeFacade, $transactionTransitionService);
        $this->settingsService = $settingsService;
    }

    public function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        $customFields = $customer->getCustomFields() ?? [];

        $cardToken = $customFields['mollie_payments']['credit_card_token'] ?? '';

        if (empty($cardToken)) {
            return $orderData;
        }

        if (!isset($orderData['payment']['cardToken']) || empty($orderData['payment']['cardToken'])) {
            $orderData['payment']['cardToken'] = $cardToken;
            $this->customerService->setCardToken($customer, '', $salesChannelContext->getContext());
        }

        return $orderData;
    }
}
