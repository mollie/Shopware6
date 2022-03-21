<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderLineIdException;
use Kiener\MolliePayments\Exception\OrderNumberNotFoundException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use Mollie\Api\Resources\Payment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService as ShopwareOrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderService implements OrderServiceInterface
{
    /**
     * @var EntityRepositoryInterface
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
     * @param EntityRepositoryInterface $orderRepository
     * @param ShopwareOrderService $swOrderService
     * @param Order $mollieOrderService
     * @param UpdateOrderCustomFields $customFieldsUpdater
     * @param UpdateOrderTransactionCustomFields $orderTransactionCustomFields
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepositoryInterface $orderRepository, ShopwareOrderService $swOrderService, Order $mollieOrderService, UpdateOrderCustomFields $customFieldsUpdater, UpdateOrderTransactionCustomFields $orderTransactionCustomFields, LoggerInterface $logger)
    {
        $this->orderRepository = $orderRepository;
        $this->swOrderService = $swOrderService;
        $this->mollieOrderService = $mollieOrderService;
        $this->updateOrderCustomFields = $customFieldsUpdater;
        $this->updateOrderTransactionCustomFields = $orderTransactionCustomFields;
        $this->logger = $logger;
    }


    /**
     * Return an order entity, enriched with associations.
     *
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    public function getOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('transactions.paymentMethod');

        /** @var OrderEntity $order */
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
     * @return string
     * @throws CouldNotExtractMollieOrderIdException
     */
    public function getMollieOrderId(OrderEntity $order): string
    {
        $mollieOrderId = $order->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? '';

        if (empty($mollieOrderId)) {
            throw new CouldNotExtractMollieOrderIdException($order->getOrderNumber());
        }

        return $mollieOrderId;
    }

    /**
     * @param OrderLineItemEntity $lineItem
     * @return string
     */
    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string
    {
        $mollieOrderLineId = $lineItem->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_LINE_KEY] ?? '';

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
     * @param string $orderTransactionId
     * @param SalesChannelContext $scContext
     */
    public function updateMollieDataCustomFields(OrderEntity $order, string $mollieOrderID, string $orderTransactionId, SalesChannelContext $scContext)
    {
        $customFields = $order->getCustomFields() ?? [];

        $customFieldsStruct = new MollieOrderCustomFieldsStruct($customFields);
        $customFieldsStruct->setMollieOrderId($mollieOrderID); # TODO i dont like that this is an optional SETTER in here!

        
        $thirdPartyPaymentId = '';
        $molliePaymentID = '';

        try {

            // Add the transaction ID to the order's custom fields
            // We might need this later on for reconciliation
            $molliePayment = $this->mollieOrderService->getCompletedPayment($mollieOrderID, $scContext->getSalesChannel()->getId());

            $molliePaymentID = $molliePayment->id;

            # check if we have a PayPal reference
            if (isset($molliePayment->details, $molliePayment->details->paypalReference)) {
                $thirdPartyPaymentId = $molliePayment->details->paypalReference;
            }

            # check if we have a Bank Transfer reference
            if (isset($molliePayment->details, $molliePayment->details->transferReference)) {
                $thirdPartyPaymentId = $molliePayment->details->transferReference;
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

        $this->updateOrderCustomFields->updateOrder(
            $order->getId(),
            $customFieldsStruct,
            $scContext
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
            $scContext
        );

    }

}
