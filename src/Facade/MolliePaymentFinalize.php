<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Service\UpdateOrderTransactionCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Kiener\MolliePayments\Struct\MollieOrderTransactionCustomFieldsStruct;
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
    /**
     * @var UpdateOrderCustomFields
     */
    private $updateOrderCustomFields;
    /**
     * @var UpdateOrderTransactionCustomFields
     */
    private $updateOrderTransactionCustomFields;
    /**
     * @var Order
     */
    private $mollieOrderService;

    public function __construct(
        MollieApiFactory                      $mollieApiFactory,
        TransactionTransitionServiceInterface $transactionTransitionService,
        OrderStatusConverter                  $orderStatusConverter,
        OrderStatusUpdater                    $orderStatusUpdater,
        SettingsService                       $settingsService,
        UpdateOrderCustomFields               $updateOrderCustomFields,
        UpdateOrderTransactionCustomFields    $updateOrderTransactionCustomFields,
        Order                                 $mollieOrderService
    )
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->orderStatusConverter = $orderStatusConverter;
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->settingsService = $settingsService;
        $this->updateOrderCustomFields = $updateOrderCustomFields;
        $this->updateOrderTransactionCustomFields = $updateOrderTransactionCustomFields;
        $this->mollieOrderService = $mollieOrderService;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @throws MissingMollieOrderIdException
     * @throws ApiException|IncompatiblePlatform|MissingMollieOrderIdException|CustomerCanceledAsyncPaymentException|PaymentNotFoundException
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

        $mollieOrder = $this->mollieOrderService->getMollieOrder(
            $mollieOrderId,
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getContext(),
            ['embed' => 'payments']
        );
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

        // Add the transaction ID to the order's custom fields
        // We might need this later on for reconciliation
        $molliePaymentId = $this->mollieOrderService->getCompletedPayment(
            $mollieOrderId,
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getContext()
        )->id;
        $customFieldsStruct->setMolliePaymentId($molliePaymentId);
        $this->updateOrderCustomFields->updateOrder($order->getId(), $customFieldsStruct, $salesChannelContext);

        // Add the transaction and order IDs to the order's transaction custom fields
        $orderTransactionCustomFields = new MollieOrderTransactionCustomFieldsStruct();
        $orderTransactionCustomFields->setMollieOrderId($customFieldsStruct->getMollieOrderId());
        $orderTransactionCustomFields->setMolliePaymentId($molliePaymentId);
        $this->updateOrderTransactionCustomFields->updateOrderTransaction(
            $transactionStruct->getOrderTransaction()->getId(),
            $orderTransactionCustomFields,
            $salesChannelContext
        );
    }
}
