<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanUpLoggerScheduledTask::class)]
final class CleanUpLoggerScheduledTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(value: '%kernel.logs_dir%')]
        private string $logDir,
        #[Autowire(service: 'monolog.logger')]
        private LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        try {
            $mollieLogDir = $this->logDir . '/mollie';
            if (! is_dir($mollieLogDir)) {
                $this->logger->info('Mollie log directory does not exist');

                return;
            }

            $logFiles = glob($mollieLogDir . '/mollie_order_*.log');
            if (empty($logFiles)) {
                $this->logger->info('No log files found to cleanup');

                return;
            }

            $orderNumbers = [];
            foreach ($logFiles as $logFile) {
                preg_match('/mollie_order_(.+)\.log/', basename($logFile), $matches);
                if (! empty($matches[1])) {
                    $orderNumbers[] = $matches[1];
                }
            }

            if (empty($orderNumbers)) {
                $this->logger->info('No valid order numbers found in log files');

                return;
            }

            $context = new Context(new SystemSource());
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'));

            $numberFilters = array_map(fn ($number) => new EqualsFilter('orderNumber', $number), $orderNumbers);
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $numberFilters));

            $paidOrders = $this->orderRepository->search($criteria, $context)->getEntities();
            $deletedCount = 0;

            foreach ($paidOrders as $order) {
                $logFile = $mollieLogDir . '/mollie_order_' . $order->getOrderNumber() . '.log';
                if (file_exists($logFile)) {
                    unlink($logFile);
                    ++$deletedCount;
                }
            }

            $this->logger->info('Cleanup logger task executed', [
                'logFilesFound' => count($logFiles),
                'paidOrdersFound' => count($paidOrders),
                'filesDeleted' => $deletedCount,
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
}
