<?php

namespace Kiener\MolliePayments\Helper;

use Kiener\MolliePayments\Service\DeliveryService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\StateMachineStateService;
use Mollie\Api\Resources\Order;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class DeliveryStateHelper
{
    private const PARAM_ID = 'id';
    private const PARAM_CUSTOM_FIELDS = 'customFields';
    private const PARAM_MOLLIE_PAYMENTS = 'mollie_payments';
    private const PARAM_IS_SHIPPED = 'is_shipped';

    /** @var DeliveryService */
    private $deliveryService;

    /** @var StateMachineRegistry */
    private $stateMachineRegistry;

    /**
     * PaymentStatusHelper constructor.
     *
     * @param DeliveryService      $deliveryService
     * @param StateMachineRegistry $stateMachineRegistry
     */
    public function __construct(
        DeliveryService $deliveryService,
        StateMachineRegistry $stateMachineRegistry
    )
    {
        $this->deliveryService = $deliveryService;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * Processes the order status of Mollie, if the order at Mollie is shipping,
     * also synchronise it to Shopware.
     *
     * @param OrderEntity $order
     * @param Order $mollieOrder
     * @param Context $context
     * @throws InconsistentCriteriaIdsException
     */
    public function shipDelivery(
        OrderEntity $order,
        Order $mollieOrder,
        Context $context
    ): void
    {
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $this->deliveryService
            ->getDeliveryByOrderId($order->getId(), $order->getVersionId());

        /**
         * Order is shipping.
         */
        if (
            $orderDelivery !== null
            && $mollieOrder->shipments()->count()
            && (
                !isset($orderDelivery->getCustomFields()[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_IS_SHIPPED])
                || $orderDelivery->getCustomFields()[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_IS_SHIPPED] === false
            )
            && (
                $orderDelivery->getStateMachineState() === null
                || $orderDelivery->getStateMachineState()->getTechnicalName() !== OrderDeliveryStates::STATE_SHIPPED
            )
        ) {
            // Transition the order to being shipped
            $this->stateMachineRegistry->transition(
                new Transition(
                    'order_delivery',
                    $orderDelivery->getId(),
                    'ship',
                    'stateId'
                ),
                $context
            );

            // Add is shipped flag to custom fields
            $this->deliveryService->updateDelivery([
                self::PARAM_ID => $orderDelivery->getId(),
                self::PARAM_CUSTOM_FIELDS => $this->deliveryService->addShippedToCustomFields($order->getCustomFields(), true),
            ], $context);
        }
    }
}