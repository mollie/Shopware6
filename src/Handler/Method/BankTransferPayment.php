<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Handler\Method;

use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Types\PaymentMethod;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BankTransferPayment extends PaymentHandler implements BankTransfer
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::BANKTRANSFER;
    public const PAYMENT_METHOD_DESCRIPTION = 'Banktransfer';
    public const DUE_DATE_MIN_DAYS = 1;
    public const DUE_DATE_MAX_DAYS = 100;

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    /** @var SettingsService */
    private $settingsService;

    public function __construct(LoggerInterface $logger, \Psr\Container\ContainerInterface $container, SettingsService $settingsService)
    {
        parent::__construct($logger, $container);
        $this->settingsService = $settingsService;
    }

    /**
     * @param array<mixed> $orderData
     *
     * @throws \Exception
     *
     * @return array<mixed>
     */
    public function processPaymentMethodSpecificParameters(array $orderData, OrderEntity $orderEntity, SalesChannelContext $salesChannelContext, CustomerEntity $customer): array
    {
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());

        $dueDateDays = (int) $settings->getPaymentMethodBankTransferDueDateDays();

        if ($dueDateDays > 0) {
            $dueDate = $settings->getPaymentMethodBankTransferDueDate();
            $orderData['expiresAt'] = $dueDate;
        }

        return $orderData;
    }
}
