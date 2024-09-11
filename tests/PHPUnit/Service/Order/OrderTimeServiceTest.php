<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Order;


use Kiener\MolliePayments\Service\Order\OrderTimeService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderTimeServiceTest extends TestCase
{
    /**
     * @param \DateTime $now
     * @param \DateTime $orderDate
     * @param bool $expected
     *
     * @dataProvider dateComparisonLogicProvider
     */
    public function testDateComparisonLogic(\DateTime $now, \DateTime $orderDate, bool $expected, int $compareValueInMinutes = 60): void
    {
        $order = $this->orderMockWithLastTransactionTimestamp($orderDate);

        $result = (new OrderTimeService($now))->isOrderAgeGreaterThan($order, $compareValueInMinutes);

        $this->assertSame($expected, $result);
    }

    private function orderMockWithLastTransactionTimestamp(\DateTime $time): OrderEntity
    {
        $entity = $this->createMock(OrderEntity::class);
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transactions = new OrderTransactionCollection([$transaction]);

        $entity->method('getTransactions')->willReturn($transactions);

        $transaction->method('getCreatedAt')->willReturn($time);

        return $entity;
    }

    public function dateComparisonLogicProvider()
    {
        return [
            'order is older than 1 hour' => [
                new \DateTime('2021-01-01 12:00:00'),
                new \DateTime('2021-01-01 10:00:00'),
                true
            ],
            'order is not older than 10 minutes' => [
                new \DateTime('2021-01-01 11:00:00'),
                new \DateTime('2021-01-01 11:11:00'),
                true,
                10
            ],
            'order is not older than 1 minute' => [
                new \DateTime('2021-01-01 12:00:00'),
                new \DateTime('2021-01-01 12:02:00'),
                true,
                1
            ],
            'order is not older than 1 hour' => [
                new \DateTime('2021-01-01 12:00:00'),
                new \DateTime('2021-01-01 11:00:00'),
                false
            ],
            'order is not older than 1 hour, but 1 second' => [
                new \DateTime('2021-01-01 12:00:00'),
                new \DateTime('2021-01-01 11:59:59'),
                false
            ],
            'order is older than a year' => [
                new \DateTime('2021-01-01 12:00:00'),
                new \DateTime('2020-01-01 12:00:00'),
                true
            ],
            'order is 2 months old' => [
                new \DateTime('2021-01-01 12:00:00'),
                new \DateTime('2020-11-01 12:00:00'),
                true
            ],
        ];
    }
}