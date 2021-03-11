<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderDeliveryService
{

    /**
     * @var EntityRepositoryInterface
     */
    private $orderDeliveryRepository;

    public function __construct(EntityRepositoryInterface $orderDeliveryRepository)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    public function getDelivery(string $orderDeliveryId, Context $context): ?OrderDeliveryEntity
    {
        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociations(['order', 'order.transactions', 'order.transactions.paymentMethod']);
        $result = $this->orderDeliveryRepository->search($criteria, $context);

        return $result->first();
    }

    public function updateCustomFields(OrderDeliveryEntity $delivery, array $values, Context $context): void
    {
        if (empty($values)) {
            return;
        }

        $customFields = $delivery->getCustomFields() ?? [];
        $customFields = array_merge($customFields, $values);

        $data = [['id' => $delivery->getId(), 'customFields' => $customFields]];

        $this->orderDeliveryRepository->update($data, $context);
    }
}
