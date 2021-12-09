<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MolliePaymentFinalize
{
    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;
    /**
     * @var TransactionTransitionServiceInterface
     */
    private $transactionTransitionService;
    /**
     * @var OrderStatusConverter
     */
    private $orderStatusConverter;
    /**
     * @var OrderStatusUpdater
     */
    private $orderStatusUpdater;
    /**
     * @var SettingsService
     */
    private $settingsService;

    public function __construct(
        MollieApiFactory                      $mollieApiFactory,
        TransactionTransitionServiceInterface $transactionTransitionService,
        OrderStatusConverter                  $orderStatusConverter,
        OrderStatusUpdater                    $orderStatusUpdater,
        SettingsService                       $settingsService
    )
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->orderStatusConverter = $orderStatusConverter;
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->settingsService = $settingsService;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @throws MissingMollieOrderIdException
     * @throws ApiException|IncompatiblePlatform|MissingMollieOrderIdException|CustomerCanceledAsyncPaymentException
     */
    public function finalize(AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext): void
    {
        $order = $transactionStruct->getOrder();
        $customFields = $order->getCustomFields() ?? [];
        $customFieldsStruct = new MollieOrderCustomFieldsStruct($customFields);
        $mollieOrderId = $customFieldsStruct->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $orderNumber = $order->getOrderNumber() ?? '-';

            throw new MissingMollieOrderIdException($orderNumber);
        }

        $apiClient = $this->mollieApiFactory->getClient($salesChannelContext->getSalesChannel()->getId());
        $mollieOrder = $apiClient->orders->get($mollieOrderId, ['embed' => 'payments']);

        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());


        $paymentStatus = $this->orderStatusConverter->getMollieOrderStatus($mollieOrder);


        # Attention
        # Our payment status will either be set by us, or automatically by Shopware using exceptions below.
        # But the order status, is something that we always have to set MANUALLY in both cases.
        # That's why we do this here, before throwing exceptions.
        $this->orderStatusUpdater->updateOrderStatus(
            $order,
            $paymentStatus,
            $settings,
            $salesChannelContext->getContext()
        );


        # now either set the payment status for successful payments
        # or make sure to throw an exception for Shopware in case
        # of failed payments.
        if (!MolliePaymentStatus::isFailedStatus($paymentStatus)) {

            $this->orderStatusUpdater->updatePaymentStatus($transactionStruct->getOrderTransaction(), $paymentStatus, $salesChannelContext->getContext());

        } else {

            $orderTransactionID = $transactionStruct->getOrderTransaction()->getUniqueIdentifier();

            # let's also create a different handling, if the customer either cancelled
            # or if the payment really failed. this will lead to a different order payment status in the end.
            if ($paymentStatus === MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED) {

                $message = sprintf('Payment for order %s (%s) was cancelled by the customer.', $order->getOrderNumber(), $mollieOrder->id);

                throw new CustomerCanceledAsyncPaymentException($orderTransactionID, $message);

            } else {

                $message = sprintf('Payment for order %s (%s) failed. The Mollie payment status was not successful for this payment attempt.', $order->getOrderNumber(), $mollieOrder->id);

                throw new AsyncPaymentFinalizeException($orderTransactionID, $message);
            }
        }
    }
}
