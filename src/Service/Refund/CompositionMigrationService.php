<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Components\RefundManager\DAL\Order\OrderExtension;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundEntity;
use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemEntity;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

class CompositionMigrationService implements CompositionMigrationServiceInterface
{
    /**
     * @var EntityRepository
     */
    private $refundItemRepository;

    public function __construct(EntityRepository $refundItemRepository)
    {
        $this->refundItemRepository = $refundItemRepository;
    }

    public function updateRefundItems(Refund $refund, OrderEntity $order, Context $context): OrderEntity
    {
        /** @var \stdClass|string $oldMetadata */
        $oldMetadata = $refund->metadata;
        if (! is_string($oldMetadata)) {
            return $order;
        }

        $oldMetadata = json_decode($oldMetadata);
        if (! property_exists($oldMetadata, 'composition') || ! is_array($oldMetadata->composition)) {
            return $order;
        }

        $oldCompositions = $oldMetadata->composition;

        /** @var ?RefundCollection $shopwareRefunds */
        $shopwareRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        if ($shopwareRefunds === null) {
            return $order;
        }

        $shopwareRefunds = $shopwareRefunds->filterByProperty('mollieRefundId', $refund->id);

        /** @var ?RefundEntity $shopwareRefund */
        $shopwareRefund = $shopwareRefunds->first();

        if ($shopwareRefund === null) {
            return $order;
        }

        $refundLineItems = $shopwareRefund->getRefundItems()->getElements();

        /*
         * Exit criteria, if we have refund line items, then we exit here
         */
        if (count($refundLineItems) > 0) {
            return $order;
        }

        /** @var ?OrderLineItemCollection $orderLineItems */
        $orderLineItems = $order->getLineItems();
        if ($orderLineItems === null) {
            return $order;
        }

        $dataToSave = [];
        foreach ($oldCompositions as $composition) {
            $label = $composition->swReference;
            if (strlen($label) === 0) {
                $label = RoundingDifferenceFixer::DEFAULT_TITLE;
            }

            $orderLineItemId = null;
            $orderLineItemVersionId = null;
            $orderLineItem = $this->filterByMollieId($orderLineItems, $composition->mollieLineId);

            if ($orderLineItem instanceof OrderLineItemEntity) {
                $orderLineItemId = $orderLineItem->getId();
                $orderLineItemVersionId = $orderLineItem->getVersionId();
            }

            $row = RefundItemEntity::createArray(
                $composition->mollieLineId,
                $label,
                $composition->quantity,
                $composition->amount,
                $orderLineItemId,
                $orderLineItemVersionId,
                $shopwareRefund->getId()
            );

            $dataToSave[] = $row;
        }

        $entityWrittenContainerEvent = $this->refundItemRepository->create($dataToSave, $context);

        /**
         * get the new inserted data from the written container event and create a new refund items collection and assign it to the refund.
         * php is using here copy by reference so the order will have the new line items inside the refund and we do not need to reload the order entity again
         */
        $refundItems = $this->createEntitiesByEvent($entityWrittenContainerEvent);
        $shopwareRefund->setRefundItems($refundItems);

        return $order;
    }

    private function createEntitiesByEvent(EntityWrittenContainerEvent $event): RefundItemCollection
    {
        $collection = new RefundItemCollection();
        $events = $event->getEvents();
        if ($events === null) {
            return $collection;
        }
        /** @var EntityWrittenEvent $writtenEvent */
        $writtenEvent = $events->first();
        $results = $writtenEvent->getWriteResults();

        foreach ($results as $result) {
            $swRefundItem = new RefundItemEntity();
            $swRefundItem->setId($result->getProperty('id'));
            $swRefundItem->setUniqueIdentifier($result->getProperty('id'));
            $swRefundItem->setQuantity($result->getProperty('quantity'));
            $swRefundItem->setLabel($result->getProperty('label'));
            $swRefundItem->setAmount($result->getProperty('amount'));
            $swRefundItem->setRefundId($result->getProperty('refundId'));
            $swRefundItem->setMollieLineId($result->getProperty('mollieLineId'));

            if ($result->getProperty('orderLineItemId') !== null && $result->getProperty('orderLineItemVersionId') !== null) {
                $swRefundItem->setOrderLineItemId($result->getProperty('orderLineItemId'));
                $swRefundItem->setOrderLineItemVersionId($result->getProperty('orderLineItemVersionId'));
            }

            $swRefundItem->setCreatedAt($result->getProperty('createdAt'));

            $collection->add($swRefundItem);
        }

        return $collection;
    }

    private function filterByMollieId(OrderLineItemCollection $lineItems, string $mollieLineId): ?OrderLineItemEntity
    {
        $foundItem = null;
        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            $customFields = $lineItem->getCustomFields();
            if ($customFields === null) {
                continue;
            }
            if (! isset($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_LINE_KEY])) {
                continue;
            }
            if ($customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_LINE_KEY] === $mollieLineId) {
                $foundItem = $lineItem;
                break;
            }
        }

        return $foundItem;
    }
}
