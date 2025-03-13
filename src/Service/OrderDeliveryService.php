<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderDeliveryService
{
    /**
     * @var EntityRepository
     */
    private $orderDeliveryRepository;


    /**
     * @param EntityRepository $orderDeliveryRepository
     */
    public function __construct(EntityRepository $orderDeliveryRepository)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    public function getDelivery(string $orderDeliveryId, Context $context): ?OrderDeliveryEntity
    {
        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.transactions.paymentMethod');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.deliveries.stateMachineState');
        $criteria->addAssociation('shippingMethod');

        return $this->orderDeliveryRepository->search($criteria, $context)->first();
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param array<mixed> $values
     * @param Context $context
     */
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
