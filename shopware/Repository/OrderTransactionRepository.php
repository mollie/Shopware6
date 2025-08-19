<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Kiener\MolliePayments\Handler\Method\BankTransferPayment;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

final class OrderTransactionRepository implements OrderTransactionRepositoryInterface
{
    /** @var EntityRepository<EntityCollection<OrderTransactionEntity> */
    private $orderTransactionRepository;
    private LoggerInterface $logger;

    /**
     * @param EntityRepository<EntityCollection<OrderTransactionEntity> $orderTransactionRepository
     */
    public function __construct($orderTransactionRepository, LoggerInterface $logger)
    {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->logger = $logger;
    }

    public function findOpenTransactions(?Context $context = null): IdSearchResult
    {
        if ($context === null) {
            $context = new Context(new SystemSource());
        }

        $date = new \DateTimeImmutable();
        $start = $date->modify(sprintf('-%d days', BankTransferPayment::DUE_DATE_MAX_DAYS + 1));
        $end = $date->modify('-5 minutes');
        $orFilterArray = [
            new EqualsFilter('stateMachineState.technicalName', OrderTransactionStates::STATE_IN_PROGRESS),
        ];
        if (defined('\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates::STATE_UNCONFIRMED')) {
            $orFilterArray[] = new EqualsFilter('stateMachineState.technicalName', OrderTransactionStates::STATE_UNCONFIRMED);
        }
        $criteria = new Criteria();
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order');

        $criteria->addFilter(new OrFilter($orFilterArray));

        $criteria->addFilter(new ContainsFilter('order.customFields', CustomFieldsInterface::MOLLIE_KEY));
        $criteria->addFilter(new RangeFilter('order.orderDateTime', [
            RangeFilter::GTE => $start->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            RangeFilter::LTE => $end->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        $criteria->addSorting(new FieldSorting('order.orderDateTime', FieldSorting::DESCENDING));
        $criteria->setLimit(10);

        $this->logger->debug('Search for orders with payment status in progress older than date', [
            'date' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $this->orderTransactionRepository->searchIds($criteria, $context);
    }
}
