<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\OrderExpiration;

use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Repository\Order\OrderRepositoryInterface;
use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Service\Order\OrderExpireService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ExpireAction
{
    private OrderRepositoryInterface $orderRepository;
    private SalesChannelRepositoryInterface $salesChannelRepository;
    private OrderExpireService $orderExpireService;
    private SettingsService $settingsService;
    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface        $orderRepository,
        SalesChannelRepositoryInterface $salesChannelRepository,
        OrderExpireService              $orderExpireService,
        SettingsService                 $settingsService,
        LoggerInterface                 $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->orderExpireService = $orderExpireService;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    public function expireOrders(Context $context): void
    {
        $this->logger->debug('Start resetting in_progress orders');

        $salesChannelsCriteria = new Criteria();
        $salesChannelsCriteria->addFilter(new EqualsFilter('active', true));

        $salesChannels = $this->salesChannelRepository->search($salesChannelsCriteria, $context);

        if ($salesChannels->count() === 0) {
            $this->logger->debug('No sales channels found');
            return;
        }

        /**
         * @var SalesChannelEntity $salesChannel
         */
        foreach ($salesChannels->getIterator() as $salesChannel) {
            $this->expireOrdersInSalesChannel($salesChannel, $context);
        }
    }

    private function expireOrdersInSalesChannel(SalesChannelEntity $salesChannelEntity, Context $context): void
    {
        $settings = $this->settingsService->getSettings($salesChannelEntity->getId());

        if ($settings->isAutomaticOrderExpire() === false) {
            $this->logger->debug('Automatic order expire is disabled for this saleschannel', ['salesChannel' => $salesChannelEntity->getName()]);
            return;
        }

        $this->logger->info('Start expire orders for saleschannel', ['salesChannel' => $salesChannelEntity->getName()]);

        $date = new \DateTime();
        $date->modify(sprintf('-%d days', (BankTransferPayment::DUE_DATE_MAX_DAYS + 1)));


        $orFilterArray = [
            new EqualsFilter('transactions.stateMachineState.technicalName', OrderTransactionStates::STATE_IN_PROGRESS),
        ];
        if (defined('\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates\OrderTransactionStates::STATE_UNCONFIRMED')) {
            $orFilterArray[] = new EqualsFilter('transactions.stateMachineState.technicalName', OrderTransactionStates::STATE_UNCONFIRMED);
        }
        $criteria = new Criteria();
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');

        $criteria->addFilter(new OrFilter($orFilterArray));

        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelEntity->getId()));
        $criteria->addFilter(new RangeFilter('orderDateTime', [RangeFilter::GTE => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        $criteria->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));
        $criteria->setLimit(10);

        $this->logger->debug('Search for orders with payment status in progress older than date', [
            'date' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $searchResult = $this->orderRepository->search($criteria, $context);
        if ($searchResult->count() === 0) {
            $this->logger->debug('No orders found with payment status in progress');
            return;
        }

        $this->logger->info('Found orders which are in progress', ['foundOrders' => $searchResult->count()]);

        /**
         * @var OrderCollection $orders
         */
        $orders = $searchResult->getEntities();
        $expiredOrders = $this->orderExpireService->cancelExpiredOrders($orders, $context);

        $this->logger->info('Expired orders with status in progress', ['expiredOrders' => $expiredOrders]);
    }
}
