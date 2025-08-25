<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

trait OrderTestBehaviour
{
    use IntegrationTestBehaviour;

    public function deleteAllOrders(Context $context): ?EntityWrittenContainerEvent
    {
        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        $criteria = new Criteria();

        $searchResult = $orderRepository->searchIds($criteria, $context);

        if ($searchResult->getTotal() === 0) {
            return null;
        }
        $ids = array_map(function (string $orderId) {
            return ['id' => $orderId];
        }, $searchResult->getIds());

        return $orderRepository->delete($ids, $context);
    }

    public function getLatestOrderId(Context $context): ?string
    {
        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $searchResult = $orderRepository->searchIds($criteria, $context);

        if ($searchResult->getTotal() === 0) {
            return null;
        }

        return $searchResult->getIds()[0];
    }

    public function updateOrder(string $orderId, array $data, Context $context): void
    {
        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');

        $data['id'] = $orderId;

        $orderRepository->upsert([$data], $context);
    }
}
