<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Components\RefundManager\DAL\Order\OrderExtension;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderLineIdException;
use Kiener\MolliePayments\Exception\OrderNumberNotFoundException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentMethod;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService as ShopwareOrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderService implements OrderServiceInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ShopwareOrderService
     */
    private $swOrderService;

    /**
     * @var Order
     */
    private $mollieOrderService;

    /**
     * @var UpdateOrderCustomFields
     */
    private $updateOrderCustomFields;

    /**
     * @var UpdateOrderTransactionCustomFields
     */
    private $updateOrderTransactionCustomFields;

    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ShopwareOrderService $swOrderService
     * @param Order $mollieOrderService
     * @param UpdateOrderCustomFields $customFieldsUpdater
     * @param UpdateOrderTransactionCustomFields $orderTransactionCustomFields
     * @param LoggerInterface $logger
     */
    public function __construct(OrderRepositoryInterface $orderRepository, ShopwareOrderService $swOrderService, Order $mollieOrderService, UpdateOrderCustomFields $customFieldsUpdater, UpdateOrderTransactionCustomFields $orderTransactionCustomFields, LoggerInterface $logger)
    {
        $this->orderRepository = $orderRepository;
        $this->swOrderService = $swOrderService;
        $this->mollieOrderService = $mollieOrderService;
        $this->updateOrderCustomFields = $customFieldsUpdater;
        $this->updateOrderTransactionCustomFields = $orderTransactionCustomFields;
        $this->logger = $logger;
    }


    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('addresses.country');     # required for FlowBuilder -> send confirm email option
        $criteria->addAssociation('billingAddress');    # important for subscription creation
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.positions.orderLineItem');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation(OrderExtension::REFUND_PROPERTY_NAME); # for refund manager


        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            return $order;
        }

        $this->logger->critical(
            sprintf('Could not find an order with id %s.', $orderId)
        );

        throw new OrderNotFoundException($orderId);
    }

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));

        $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();

        if (is_string($orderId)) {
            return $this->getOrder($orderId, $context);
        }

        $this->logger->critical(
            sprintf('Could not find an order with order number %s.', $orderNumber)
        );

        throw new OrderNumberNotFoundException($orderNumber);
    }

    /**
     * @param OrderEntity $order
     * @throws CouldNotExtractMollieOrderIdException
     * @return string
     */
    public function getMollieOrderId(OrderEntity $order): string
    {
        $customFields = $order->getCustomFields();

        $mollieOrderId = '';

        if ($customFields !== null) {
            $mollieOrderId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? '';
        }

        if (empty($mollieOrderId)) {
            throw new CouldNotExtractMollieOrderIdException((string)$order->getOrderNumber());
        }

        return $mollieOrderId;
    }

    /**
     * @param OrderLineItemEntity $lineItem
     * @return string
     */
    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string
    {
        $customFields = $lineItem->getCustomFields();

        $mollieOrderLineId = '';

        if ($customFields !== null) {
            $mollieOrderLineId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_LINE_KEY] ?? '';
        }

        if (empty($mollieOrderLineId)) {
            throw new CouldNotExtractMollieOrderLineIdException($lineItem->getId());
        }

        return $mollieOrderLineId;
    }

    /**
     * @param DataBag $data
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity
    {
        $orderId = $this->swOrderService->createOrder($data, $context);

        $order = $this->getOrder($orderId, $context->getContext());

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }


    /**
     * @param OrderEntity $order
     * @param string $mollieOrderID
     * @param string $molliePaymentId
     * @param string $orderTransactionId
     * @param Context $context
     * @return void
     */
    public function updateMollieDataCustomFields(OrderEntity $order, string $mollieOrderID, string $molliePaymentId, string $orderTransactionId, Context $context): void
    {
        $customFieldsStruct = new OrderAttributes($order);
        $customFieldsStruct->setMollieOrderId($mollieOrderID); # TODO i dont like that this is an optional SETTER in here!


        $thirdPartyPaymentId = '';
        $molliePaymentID = '';
        $creditCardDetails = null;

        try {
            // Add the transaction ID to the order's custom fields
            // We might need this later on for reconciliation
            $molliePayment = $this->mollieOrderService->getCompletedPayment($mollieOrderID, $molliePaymentId, $order->getSalesChannelId());

            $molliePaymentID = $molliePayment->id;

            # check if we have a PayPal reference
            if (isset($molliePayment->details, $molliePayment->details->paypalReference)) {
                $thirdPartyPaymentId = $molliePayment->details->paypalReference;
            }

            # check if we have a Bank Transfer reference
            if (isset($molliePayment->details, $molliePayment->details->transferReference)) {
                $thirdPartyPaymentId = $molliePayment->details->transferReference;
            }

            # check for creditcard
            if (isset($molliePayment->method, $molliePayment->details) && $molliePayment->method === PaymentMethod::CREDITCARD) {
                $creditCardDetails = $molliePayment->details;
            }
        } catch (PaymentNotFoundException $ex) {
            # some orders like OPEN bank transfer have no completed payments
            # so this is a usual case, where we just need to skip this process
            # but we still want to update the basic data below
        }


        # ----------------------------------
        # Update Order Custom Fields

        $customFieldsStruct->setMolliePaymentId($molliePaymentID);
        $customFieldsStruct->setThirdPartyPaymentId($thirdPartyPaymentId);
        $customFieldsStruct->setCreditCardDetails($creditCardDetails);

        $this->updateOrderCustomFields->updateOrder(
            $order->getId(),
            $customFieldsStruct,
            $context
        );

        # ----------------------------------
        # Update Order Transaction Custom Fields

        // Add the transaction and order IDs to the order's transaction custom fields
        $orderTransactionCustomFields = new OrderTransactionAttributes();
        $orderTransactionCustomFields->setMollieOrderId($customFieldsStruct->getMollieOrderId());
        $orderTransactionCustomFields->setMolliePaymentId($molliePaymentID);
        $orderTransactionCustomFields->setThirdPartyPaymentId($thirdPartyPaymentId);

        $this->updateOrderTransactionCustomFields->updateOrderTransaction(
            $orderTransactionId,
            $orderTransactionCustomFields,
            $context
        );
    }


    /**
     * @param OrderEntity $order
     * @param string $orderTransactionId
     * @param string $mollieOrderID
     * @param string $swSubscriptionId
     * @param string $mollieSubscriptionId
     * @param Payment $molliePayment
     * @param Context $context
     * @return void
     */
    public function updateMollieData(OrderEntity $order, string $orderTransactionId, string $mollieOrderID, string $swSubscriptionId, string $mollieSubscriptionId, Payment $molliePayment, Context $context)
    {
        $thirdPartyPaymentId = '';

        # check if we have a PayPal reference
        if (isset($molliePayment->details, $molliePayment->details->paypalReference)) {
            $thirdPartyPaymentId = $molliePayment->details->paypalReference;
        }
        # check if we have a Bank Transfer reference
        if (isset($molliePayment->details, $molliePayment->details->transferReference)) {
            $thirdPartyPaymentId = $molliePayment->details->transferReference;
        }

        # --------------------------------------------------------------------------------

        $orderAttributes = new OrderAttributes($order);
        $orderAttributes->setMollieOrderId($mollieOrderID);
        $orderAttributes->setMolliePaymentId($molliePayment->id);
        $orderAttributes->setSubscriptionData($swSubscriptionId, $mollieSubscriptionId);
        $orderAttributes->setThirdPartyPaymentId($thirdPartyPaymentId);

        $this->updateOrderCustomFields->updateOrder(
            $order->getId(),
            $orderAttributes,
            $context
        );

        # --------------------------------------------------------------------------------

        // Add the transaction and order IDs to the order's transaction custom fields
        $orderTransactionCustomFields = new OrderTransactionAttributes();
        $orderTransactionCustomFields->setMollieOrderId($mollieOrderID);
        $orderTransactionCustomFields->setMolliePaymentId($molliePayment->id);
        $orderTransactionCustomFields->setThirdPartyPaymentId($thirdPartyPaymentId);

        $this->updateOrderTransactionCustomFields->updateOrderTransaction(
            $orderTransactionId,
            $orderTransactionCustomFields,
            $context
        );
    }
}
