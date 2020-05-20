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
    )
    {
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
     * Returns a delivery by id and version.
     *
     * @param $deliveryId
     * @param $versionId
     * @param Context|null $context
     * @return OrderDeliveryEntity|null
     */
    public function getDeliveryById(
        $deliveryId,
        $versionId = null,
        Context $context = null
    ): ?OrderDeliveryEntity
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
     * Returns a delivery by an order id.
     *
     * @param string       $orderId
     * @param string|null  $orderVersionId
     * @param Context|null $context
     *
     * @return OrderDeliveryEntity|null
     */
    public function getDeliveryByOrderId(
        string $orderId,
        string $orderVersionId = null,
        Context $context = null
    ): ?OrderDeliveryEntity
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
     * Adds shipped variable to custom fields.
     *
     * @param array $customFields
     * @param bool  $shipped
     *
     * @return array
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
     * @param array $data
     * @param Context|null $context
     * @return EntityWrittenContainerEvent
     */
    public function updateDelivery(
        array $data,
        Context $context = null
    ): EntityWrittenContainerEvent
    {
        return $this->getRepository()->update(
            [$data],
            $context ?? Context::createDefaultContext()
        );
    }
}