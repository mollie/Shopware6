<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Components\RefundManager\DAL\Order\OrderExtension;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundEntity;
use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemCollection;
use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemEntity;
use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

class CompositionRepairService implements CompositionRepairServiceInterface
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
        if (! property_exists($oldMetadata, 'composition') && ! is_array($oldMetadata->composition)) {
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
            $reference = $composition->swReference;
            if (strlen($reference) === 0) {
                $reference = RoundingDifferenceFixer::DEFAULT_TITLE;
            }

            $row = [
                'type' => $oldMetadata->type,
                'mollieLineId' => $composition->mollieLineId,
                'reference' => $reference,
                'quantity' => $composition->quantity,
                'amount' => $composition->amount,
                'refundId' => $shopwareRefund->getId(),
                'oderLineItemId' => null,
                'oderLineItemVersionId' => null,
            ];
            $orderLineItem = $this->filterByMollieId($orderLineItems, $composition->mollieLineId);
            if ($orderLineItem instanceof OrderLineItemEntity) {
                $row['orderLineItemId'] = $orderLineItem->getId();
                $row['orderLineItemVersionId'] = $orderLineItem->getVersionId();
            }


            $dataToSave[] = $row;
        }
        $entityWrittenContainerEvent = $this->refundItemRepository->create($dataToSave, $context);
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
            $entity = new RefundItemEntity();
            $entity->setId($result->getProperty('id'));
            $entity->setUniqueIdentifier($result->getProperty('id'));
            $entity->setQuantity($result->getProperty('quantity'));
            $entity->setReference($result->getProperty('reference'));
            $entity->setAmount($result->getProperty('amount'));
            $entity->setRefundId($result->getProperty('refundId'));
            $entity->setMollieLineId($result->getProperty('mollieLineId'));
            if ($result->getProperty('orderLineItemId') !== null && $result->getProperty('orderLineItemVersionId') !== null) {
                $entity->setOrderLineItemId($result->getProperty('orderLineItemId'));
                $entity->setOrderLineItemVersionId($result->getProperty('orderLineItemVersionId'));
            }

            $entity->setType($result->getProperty('type'));
            $entity->setCreatedAt($result->getProperty('createdAt'));
            $collection->add($entity);
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
            if (! isset($customFields['mollie_payments']['order_line_id'])) {
                continue;
            }
            if ($customFields['mollie_payments']['order_line_id'] === $mollieLineId) {
                $foundItem = $lineItem;
                break;
            }
        }
        return $foundItem;
    }
}
