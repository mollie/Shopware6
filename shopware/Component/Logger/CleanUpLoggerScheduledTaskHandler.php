<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanUpLoggerScheduledTask::class)]
final class CleanUpLoggerScheduledTaskHandler extends ScheduledTaskHandler
{
    private const MAX_DELETE_PER_RUN = 100;

    /**
     * Transaction states that mark an order as successfully paid.
     */
    private const SUCCESS_STATES = [
        OrderTransactionStates::STATE_PAID,
        OrderTransactionStates::STATE_AUTHORIZED,
    ];

    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        private OrderLogStorage $logStorage,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        try {
            $loggerSettings = $this->settingsService->getLoggerSettings();
            $successDays = $loggerSettings->getLogSuccessDays();
            $failedDays = $loggerSettings->getLogFailedDays();

            $orderNumbers = $this->logStorage->listOrderNumbers(self::MAX_DELETE_PER_RUN);
            if ($orderNumbers === []) {
                return;
            }

            $successStateByOrderNumber = $this->fetchSuccessStateByOrderNumber($orderNumbers);

            $deletedCount = 0;
            foreach ($orderNumbers as $orderNumber) {
                $modifiedTime = $this->logStorage->getModifiedTime($orderNumber);
                if ($modifiedTime === null) {
                    continue;
                }

                $isSuccess = $successStateByOrderNumber[$orderNumber] ?? false;
                $daysToKeep = $isSuccess ? $successDays : $failedDays;
                $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);

                if ($modifiedTime >= $cutoffTime) {
                    continue;
                }

                try {
                    $this->logStorage->delete($orderNumber);
                    ++$deletedCount;
                } catch (\Throwable $e) {
                    $this->logger->warning('Could not delete order log file', [
                        'orderNumber' => $orderNumber,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->debug('Cleanup logger task executed', [
                'filesDeleted' => $deletedCount,
                'filesChecked' => count($orderNumbers),
                'successDays' => $successDays,
                'failedDays' => $failedDays,
                'maxPerRun' => self::MAX_DELETE_PER_RUN,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error in cleanup logger task: ' . $e->getMessage());
        }
    }

    /**
     * @return iterable<mixed>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            CleanUpLoggerScheduledTask::class,
        ];
    }

    /**
     * Resolves the latest transaction state for a batch of order numbers in a
     * single DAL query. Orders that are no longer in the database (or have no
     * transaction) are treated as not successful, so their logs are deleted
     * after the failed-retention time as a safe default.
     *
     * @param list<string> $orderNumbers
     *
     * @return array<string,bool> orderNumber => isSuccessful
     */
    private function fetchSuccessStateByOrderNumber(array $orderNumbers): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('orderNumber', $orderNumbers));
        $criteria->addAssociation('transactions');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $result = [];
        $orders = $this->orderRepository->search($criteria, Context::createDefaultContext());
        foreach ($orders as $order) {
            if (! $order instanceof OrderEntity) {
                continue;
            }

            $result[(string) $order->getOrderNumber()] = $this->isOrderSuccessful($order);
        }

        return $result;
    }

    private function isOrderSuccessful(OrderEntity $order): bool
    {
        $transactions = $order->getTransactions();
        if (! $transactions instanceof OrderTransactionCollection) {
            return false;
        }

        $transaction = $transactions->first();
        if (! $transaction instanceof OrderTransactionEntity) {
            return false;
        }

        $state = $transaction->getStateMachineState();
        if ($state === null) {
            return false;
        }

        return in_array($state->getTechnicalName(), self::SUCCESS_STATES, true);
    }
}
