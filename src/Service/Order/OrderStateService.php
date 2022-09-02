<?php

namespace Kiener\MolliePayments\Service\Order;

use Exception;
use Kiener\MolliePayments\Service\Transition\OrderTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderStateService
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var OrderTransitionServiceInterface */
    protected $orderTransitionService;


    /**
     * @param LoggerInterface $logger
     * @param OrderTransitionServiceInterface $orderTransitionService
     */
    public function __construct(LoggerInterface $logger, OrderTransitionServiceInterface $orderTransitionService)
    {
        $this->logger = $logger;
        $this->orderTransitionService = $orderTransitionService;
    }

    /**
     * Handle order state automation.
     *
     * @param OrderEntity $order
     * @param string $orderState
     * @param Context $context
     *
     * @return bool
     */
    public function setOrderState(OrderEntity $order, string $orderState, Context $context): bool
    {
        // if order state is skip we don't set a new order state
        if ($orderState === MollieSettingStruct::ORDER_STATE_SKIP) {
            return false;
        }

        $currentStatus = ($order->getStateMachineState() instanceof StateMachineStateEntity) ? $order->getStateMachineState()->getTechnicalName() : '';

        // if current state is same as status that should be set, we don't need to do a transition
        if ($currentStatus === $orderState) {
            return false;
        }

        try {
            switch ($orderState) {
                case OrderStates::STATE_OPEN:
                    $this->orderTransitionService->openOrder($order, $context);
                    break;
                case OrderStates::STATE_IN_PROGRESS:
                    $this->orderTransitionService->processOrder($order, $context);
                    break;
                case OrderStates::STATE_COMPLETED:
                    $this->orderTransitionService->completeOrder($order, $context);
                    break;
                case OrderStates::STATE_CANCELLED:
                    $this->orderTransitionService->cancelOrder($order, $context);
                    break;
                default:
                    return false;
            }

            return true;
        } catch (Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                [
                    'function' => 'payment-automate-order-state',
                ]
            );
        }

        return false;
    }
}
