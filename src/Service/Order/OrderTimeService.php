<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use DateTime;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderTimeService
{
    /**
     * @var DateTime
     */
    private $now;

    public function __construct(?DateTime $now = null)
    {
        $this->now = $now ?? new DateTime();
    }

    /**
     * Checks if the age of the last transaction of the order is greater than the specified number of hours.
     *
     * @param OrderEntity $order The order entity to check.
     * @param int $hours The number of hours to compare against.
     *
     * @return bool Returns true if the order is older than the specified number of hours, false otherwise.
     */
    public function isOrderAgeGreaterThan(OrderEntity $order, int $hours): bool
    {
        $transactions = $order->getTransactions();

        if ($transactions === null || count($transactions) === 0) {
            return false;
        }

        /** @var ?OrderTransactionEntity $lastTransaction */
        $lastTransaction = $transactions->last();

        if ($lastTransaction === null) {
            return false;
        }

        $transitionDate = $lastTransaction->getCreatedAt();

        if ($transitionDate === null) {
            return false;
        }

        $interval = $this->now->diff($transitionDate);
        $diffInHours = $interval->h + ($interval->days * 24);

        return $diffInHours > $hours;
    }
}
