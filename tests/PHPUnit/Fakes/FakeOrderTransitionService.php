<?php declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;


use Exception;
use Kiener\MolliePayments\Service\Transition\OrderTransitionServiceInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class FakeOrderTransitionService implements OrderTransitionServiceInterface
{
    public $states = [];

    public $orders = [];

    public $contexts = [];

    public $returns = [];

    /** @var Exception|null */
    public $exception = null;

    public function getAvailableTransitions(OrderEntity $order, Context $context): array
    {
        $this->orders[] = $order;
        $this->contexts[] = $context;

        return array_shift($this->returns);
    }

    public function openOrder(OrderEntity $order, Context $context): void
    {
        $this->states[] = 'open';
        $this->orders[] = $order;
        $this->contexts[] = $context;

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }

    public function processOrder(OrderEntity $order, Context $context): void
    {
        $this->states[] = 'in_progress';
        $this->orders[] = $order;
        $this->contexts[] = $context;

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }

    public function completeOrder(OrderEntity $order, Context $context): void
    {
        $this->states[] = 'completed';
        $this->orders[] = $order;
        $this->contexts[] = $context;

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }

    public function cancelOrder(OrderEntity $order, Context $context): void
    {
        $this->states[] = 'cancelled';
        $this->orders[] = $order;
        $this->contexts[] = $context;

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }
}
