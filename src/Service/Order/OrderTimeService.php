<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Order;

use DateTime;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;

class OrderTimeService
{
    /**
     * @var DateTime
     */
    private $now;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger, ?DateTime $now = null)
    {
        $this->now = $now ?? new DateTime();
        $this->logger = $logger;
    }

    /**
     * Checks if the age of the last transaction of the order is greater than the specified number of hours.
     *
     * @param OrderEntity $order the order entity to check
     * @param int $minutes the number of minutes to compare against
     *
     * @return bool returns true if the order is older than the specified number of hours, false otherwise
     */
    public function isOrderAgeGreaterThan(OrderEntity $order, int $minutes): bool
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

        $transitionDate = $lastTransaction->getUpdatedAt() ?? $lastTransaction->getCreatedAt();

        if ($transitionDate === null) {
            return false;
        }

        $interval = $this->now->diff($transitionDate);
        $diffInHours = $interval->h + ($interval->days * 24);
        $diffInMinutes = $interval->i + ($diffInHours * 60);

        $this->logger->debug('Check if order is expired', [
            'lastTransactionTime' => $transitionDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'now' => $this->now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'diffInMinutes' => $diffInMinutes,
            'minutes' => $minutes,
        ]);

        return $diffInMinutes > $minutes;
    }
}
