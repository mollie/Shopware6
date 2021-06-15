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

class BankTransferPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANKTRANSFER;
    public const PAYMENT_METHOD_DESCRIPTION = 'Banktransfer';
    public const DUE_DATE_MIN_DAYS = 1;
    public const DUE_DATE_MAX_DAYS = 100;

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    /** @var SettingsService */
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

    /**
     * @param array $orderData
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @param LocaleEntity $locale
     *
     * @return array
     */
    public function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());
        $dueDate = $settings->getPaymentMethodBankTransferDueDate();

        if (!empty($dueDate)) {

            $orderData['expiresAt'] = $dueDate;
        }

        return $orderData;
    }
}
