<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Service\UpdateOrderTransactionCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MolliePaymentFinalize
{

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


    /**
     * @param OrderStatusConverter $orderStatusConverter
     * @param OrderStatusUpdater $orderStatusUpdater
     * @param SettingsService $settingsService
     * @param UpdateOrderCustomFields $updateOrderCustomFields
     * @param UpdateOrderTransactionCustomFields $updateOrderTransactionCustomFields
     * @param Order $mollieOrderService
     */
    public function __construct(OrderStatusConverter $orderStatusConverter, OrderStatusUpdater $orderStatusUpdater, SettingsService $settingsService, UpdateOrderCustomFields $updateOrderCustomFields, UpdateOrderTransactionCustomFields $updateOrderTransactionCustomFields, Order $mollieOrderService)
    {
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

        # now update the custom fields of the order
        # we want to have as much information as possible in the shopware order
        # this includes the Mollie Payment ID and maybe additional references
        $this->updateCustomField(
            $order,
            $mollieOrderId,
            $customFieldsStruct,
            $transactionStruct,
            $salesChannelContext
        );
    }


    /**
     * @param OrderEntity $order
     * @param string $mollieOrderID
     * @param MollieOrderCustomFieldsStruct $customFieldsStruct
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $scContext
     * @return void
     */
    private function updateCustomField(OrderEntity $order, string $mollieOrderID, MollieOrderCustomFieldsStruct $customFieldsStruct, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $scContext)
    {
        // Add the transaction ID to the order's custom fields
        // We might need this later on for reconciliation
        $molliePayment = $this->mollieOrderService->getCompletedPayment(
            $mollieOrderID,
            $scContext->getSalesChannel()->getId()
        );


        $thirdPartyPaymentId = '';

        # check if we have a PayPal reference
        if (isset($molliePayment->details, $molliePayment->details->paypalReference)) {
            $thirdPartyPaymentId = $molliePayment->details->paypalReference;
        }

        # check if we have a Bank Transfer reference
        if (isset($molliePayment->details, $molliePayment->details->transferReference)) {
            $thirdPartyPaymentId = $molliePayment->details->transferReference;
        }


        # ----------------------------------
        # Update Order Custom Fields

        $customFieldsStruct->setMolliePaymentId($molliePayment->id);
        $customFieldsStruct->setThirdPartyPaymentId($thirdPartyPaymentId);

        $this->updateOrderCustomFields->updateOrder(
            $order->getId(), $customFieldsStruct,
            $scContext
        );

        # ----------------------------------
        # Update Order Transaction Custom Fields

        // Add the transaction and order IDs to the order's transaction custom fields
        $orderTransactionCustomFields = new OrderTransactionAttributes();
        $orderTransactionCustomFields->setMollieOrderId($customFieldsStruct->getMollieOrderId());
        $orderTransactionCustomFields->setMolliePaymentId($molliePayment->id);
        $orderTransactionCustomFields->setThirdPartyPaymentId($thirdPartyPaymentId);

        $this->updateOrderTransactionCustomFields->updateOrderTransaction(
            $transactionStruct->getOrderTransaction()->getId(),
            $orderTransactionCustomFields,
            $scContext
        );
    }

}
