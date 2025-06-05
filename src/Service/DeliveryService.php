<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DeliveryService
{
    private const PARAM_MOLLIE_PAYMENTS = 'mollie_payments';
    private const PARAM_IS_SHIPPED = 'is_shipped';

    /**
     * @var EntityRepository<EntityCollection<OrderDeliveryEntity>>
     */
    private $orderDeliveryRepository;

    /**
     * Creates a new instance of the transaction service.
     *
     * @param EntityRepository<EntityCollection<OrderDeliveryEntity>> $orderDeliveryRepository
     */
    public function __construct($orderDeliveryRepository)
    {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /**
     * @param string $deliveryId
     * @param null|string $versionId
     */
    public function getDeliveryById($deliveryId, $versionId = null, ?Context $context = null): ?OrderDeliveryEntity
    {
        $deliveryCriteria = new Criteria([$deliveryId]);

        if ($versionId !== null) {
            $deliveryCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        }

        $deliveryCriteria->addAssociation('order');

        $result = $this->orderDeliveryRepository->search($deliveryCriteria, $context ?? Context::createDefaultContext());

        /** @var null|OrderDeliveryEntity */
        return $result->get($deliveryId);
    }

    public function getDeliveryByOrderId(string $orderId, ?string $orderVersionId = null, ?Context $context = null): ?OrderDeliveryEntity
    {
        $deliveryCriteria = new Criteria();
        $deliveryCriteria->addFilter(new EqualsFilter('orderId', $orderId));

        if ($orderVersionId !== null) {
            $deliveryCriteria->addFilter(new EqualsFilter('orderVersionId', $orderVersionId));
        }

        $deliveryCriteria->addAssociation('order');

        return $this->orderDeliveryRepository
            ->search($deliveryCriteria, $context ?? Context::createDefaultContext())->first()
        ;
    }

    /**
     * @param array<mixed> $customFields
     *
     * @return array<mixed>
     */
    public function addShippedToCustomFields(array $customFields, bool $shipped = false): array
    {
        if (! isset($customFields[self::PARAM_MOLLIE_PAYMENTS])) {
            $customFields[self::PARAM_MOLLIE_PAYMENTS] = [];
        }

        $customFields[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_IS_SHIPPED] = $shipped;

        return $customFields;
    }

    /**
     * Updates a delivery in the database.
     *
     * @param array<mixed> $data
     */
    public function updateDelivery(array $data, ?Context $context = null): EntityWrittenContainerEvent
    {
        return $this->orderDeliveryRepository->update(
            [$data],
            $context ?? Context::createDefaultContext()
        );
    }
}
