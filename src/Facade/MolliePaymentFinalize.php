<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MissingMollieOrderId;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MolliePaymentFinalize
{
    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;
    /**
     * @var PaymentStatusHelper
     */
    private PaymentStatusHelper $paymentStatusHelper;
    /**
     * @var TransactionTransitionServiceInterface
     */
    private TransactionTransitionServiceInterface $transactionTransitionService;

    public function __construct(
        MollieApiFactory $mollieApiFactory,
        PaymentStatusHelper $paymentStatusHelper,
        TransactionTransitionServiceInterface $transactionTransitionService
    )
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->transactionTransitionService = $transactionTransitionService;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @throws MissingMollieOrderId
     * @throws ApiException|IncompatiblePlatform|MissingMollieOrderId|CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext)
    {
        $order = $transactionStruct->getOrder();
        $customFields = new MollieOrderCustomFieldsStruct($order->getCustomFields());
        $mollieOrderId = $customFields->getMollieOrderId();

        if (empty($mollieOrderId)) {
            // Set the error message
            $errorMessage = sprintf('The Mollie id for order %s could not be found', $order->getOrderNumber());

            throw new MissingMollieOrderId($errorMessage);
        }

        $apiClient = $this->mollieApiFactory->getClient($salesChannelContext->getSalesChannelId(), $salesChannelContext->getContext());
        $mollieOrder = $apiClient->orders->get($mollieOrderId, ['embed' => 'payments']);

        $paymentStatus = $this->paymentStatusHelper->processPaymentStatus(
            $transactionStruct->getOrderTransaction(),
            $order,
            $mollieOrder,
            $salesChannelContext->getContext()
        );

        if ($paymentStatus === PaymentStatus::STATUS_CANCELED || $paymentStatus === PaymentStatus::STATUS_FAILED) {
            $this->transactionTransitionService->reOpenTransaction(
                $transactionStruct->getOrderTransaction(),
                $salesChannelContext->getContext()
            );

            throw new CustomerCanceledAsyncPaymentException(
                $transactionStruct->getOrderTransaction()->getUniqueIdentifier(),
                sprintf(
                    'Payment for order %s (%s) was cancelled by the customer.',
                    $order->getOrderNumber(),
                    $mollieOrder->id
                )
            );
        }
    }
}
