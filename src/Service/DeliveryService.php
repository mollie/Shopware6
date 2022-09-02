<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DeliveryService
{
    private const PARAM_MOLLIE_PAYMENTS = 'mollie_payments';
    private const PARAM_IS_SHIPPED = 'is_shipped';

    /** @var EntityRepositoryInterface $orderDeliveryRepository */
    private $orderDeliveryRepository;

    /**
     * Creates a new instance of the transaction service.
     *
     * @param EntityRepositoryInterface $orderDeliveryRepository
     */
    public function __construct(
        EntityRepositoryInterface $orderDeliveryRepository
    ) {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    /**
     * Returns the order delivery repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->orderDeliveryRepository;
    }

    /**
     * @param string $deliveryId
     * @param null|string $versionId
     * @param null|Context $context
     * @return null|OrderDeliveryEntity
     */
    public function getDeliveryById($deliveryId, $versionId = null, Context $context = null): ?OrderDeliveryEntity
    {
        $deliveryCriteria = new Criteria([$deliveryId]);

        if ($versionId !== null) {
            $deliveryCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        }

        $deliveryCriteria->addAssociation('order');

        return $this->getRepository()
            ->search($deliveryCriteria, $context ?? Context::createDefaultContext())->get($deliveryId);
    }

    /**
     * @param string $orderId
     * @param null|string $orderVersionId
     * @param null|Context $context
     * @return null|OrderDeliveryEntity
     */
    public function getDeliveryByOrderId(string $orderId, string $orderVersionId = null, Context $context = null): ?OrderDeliveryEntity
    {
        $deliveryCriteria = new Criteria();
        $deliveryCriteria->addFilter(new EqualsFilter('orderId', $orderId));

        if ($orderVersionId !== null) {
            $deliveryCriteria->addFilter(new EqualsFilter('orderVersionId', $orderVersionId));
        }

        $deliveryCriteria->addAssociation('order');

        return $this->getRepository()
            ->search($deliveryCriteria, $context ?? Context::createDefaultContext())->first();
    }

    /**
     * @param array<mixed> $customFields
     * @param bool $shipped
     * @return array<mixed>
     */
    public function addShippedToCustomFields(array $customFields, bool $shipped = false): array
    {
        if (!isset($customFields[self::PARAM_MOLLIE_PAYMENTS])) {
            $customFields[self::PARAM_MOLLIE_PAYMENTS] = [];
        }

        $customFields[self::PARAM_MOLLIE_PAYMENTS][self::PARAM_IS_SHIPPED] = $shipped;

        return $customFields;
    }

    /**
     * Updates a delivery in the database.
     *
     * @param array<mixed> $data
     * @param null|Context $context
     * @return EntityWrittenContainerEvent
     */
    public function updateDelivery(array $data, Context $context = null): EntityWrittenContainerEvent
    {
        return $this->getRepository()->update(
            [$data],
            $context ?? Context::createDefaultContext()
        );
    }
}
