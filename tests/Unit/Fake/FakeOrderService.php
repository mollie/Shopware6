<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Symfony\Component\HttpFoundation\ParameterBag;

final class FakeOrderService extends OrderService
{
    /** @var list<array{orderDeliveryId: string, transition: string}> */
    private array $deliveryTransitions = [];

    private bool $shouldThrowIllegalTransition = false;

    public function __construct()
    {
    }

    public function setShouldThrowIllegalTransition(bool $shouldThrow): void
    {
        $this->shouldThrowIllegalTransition = $shouldThrow;
    }

    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
    }

    public function orderStateTransition(string $orderId, string $transition, ParameterBag $data, Context $context): StateMachineStateEntity
    {
    }

    public function orderTransactionStateTransition(string $orderTransactionId, string $transition, ParameterBag $data, Context $context): StateMachineStateEntity
    {
    }

    public function orderDeliveryStateTransition(string $orderDeliveryId, string $transition, ParameterBag $data, Context $context): StateMachineStateEntity
    {
        $this->deliveryTransitions[] = ['orderDeliveryId' => $orderDeliveryId, 'transition' => $transition];

        if ($this->shouldThrowIllegalTransition) {
            throw new IllegalTransitionException('shipped', $transition, ['ship_partially']);
        }

        return new StateMachineStateEntity();
    }

    /**
     * @return list<array{orderDeliveryId: string, transition: string}>
     */
    public function getDeliveryTransitions(): array
    {
        return $this->deliveryTransitions;
    }

    public function isPaymentChangeableByTransactionState(OrderEntity $order): bool
    {
    }
}
