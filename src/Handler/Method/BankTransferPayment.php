<?php

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Facade\MolliePaymentDoPay;
use Kiener\MolliePayments\Facade\MolliePaymentFinalize;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Mollie\Api\Types\PaymentMethod;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
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


    /**
     * @param LoggerInterface $logger
     * @param \Psr\Container\ContainerInterface $container
     * @param SettingsService $settingsService
     */
    public function __construct(LoggerInterface $logger, \Psr\Container\ContainerInterface $container, SettingsService $settingsService)
    {
        parent::__construct($logger, $container);
        $this->settingsService = $settingsService;
    }

    /**
     * @param array<mixed> $orderData
     * @param OrderEntity $orderEntity
     * @param SalesChannelContext $salesChannelContext
     * @param CustomerEntity $customer
     * @throws \Exception
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());
        $dueDate = $settings->getPaymentMethodBankTransferDueDate();

        if (!empty($dueDate)) {
            $orderData['expiresAt'] = $dueDate;
        }

        return $orderData;
    }
}
