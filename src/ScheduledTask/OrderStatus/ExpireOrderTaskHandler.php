<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\OrderStatus;

use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Kiener\MolliePayments\Repository\ScheduledTask\ScheduledTaskRepositoryInterface;
use Kiener\MolliePayments\Service\Order\OrderExpireService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

#[\Symfony\Component\Messenger\Attribute\AsMessageHandler(handles: ExpireOrderTask::class)]
class ExpireOrderTaskHandler extends ScheduledTaskHandler
{
    private LoggerInterface $logger;
    private OrderRepositoryInterface $orderRepository;
    private OrderExpireService $orderExpireService;

    public function __construct(ScheduledTaskRepositoryInterface $scheduledTaskRepository, OrderRepositoryInterface $orderRepository, OrderExpireService $orderExpireService, LoggerInterface $logger)
    {
        /** @phpstan-ignore-next-line  */
        parent::__construct($scheduledTaskRepository->getRepository(), $logger);
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderExpireService = $orderExpireService;
    }

    public function run(): void
    {
        $this->logger->info('Start resetting in_progress orders');

        $context = new Context(new SystemSource());

        $criteria = new Criteria();
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addFilter(new EqualsFilter('transactions.stateMachineState.technicalName', OrderStates::STATE_IN_PROGRESS));
        $criteria->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));
        $criteria->setLimit(10);

        $this->logger->debug('Search for orders which are in progress state');

        $searchResult = $this->orderRepository->search($criteria, $context);
        if ($searchResult->count() === 0) {
            $this->logger->debug('No in progress orders found');
            return;
        }

        $this->logger->info('Found orders which are in progress', ['foundOrders' => $searchResult->count()]);

        /**
         * @var OrderCollection $orders
         */
        $orders = $searchResult->getEntities();
        $resetted = $this->orderExpireService->cancelExpiredOrders($orders, $context);

        $this->logger->info('Rested expired orders', ['restedOrders' => $resetted]);
    }

    /**
     * @return iterable<mixed>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            ExpireOrderTask::class
        ];
    }
}
