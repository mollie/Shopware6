<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class RefundCreditNoteService
{
    /**
     * @var EntityRepository<OrderCollection>
     */
    private $orderRepository;

    /**
     * @var EntityRepository<OrderLineItemCollection>
     */
    private $orderLineItemRepository;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var string
     */
    private $suffix;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<OrderLineItemCollection> $orderLineItemRepository
     * @param SettingsService $settingsService
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $orderLineItemRepository,
        SettingsService  $settingsService,
        LoggerInterface  $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $settings = $settingsService->getSettings();
        $this->enabled = $settings->isRefundManagerCreateCreditNotesEnabled();
        $this->prefix = $settings->getRefundManagerCreateCreditNotesPrefix();
        $this->suffix = $settings->getRefundManagerCreateCreditNotesSuffix();
        $this->logger = $logger;
    }

    /**
     * @param string $orderId
     * @param string $refundId
     * @param float $unitPrice
     * @param int $quantity
     * @param float $totalAmount
     * @param null|OrderLineItemEntity $orderLineItemEntity
     * @param null|OrderDeliveryEntity $orderDeliveryEntity
     * @return array<mixed>
     */
    private function getLineItemArray(string $orderId, string $refundId, float $unitPrice, int $quantity, float $totalAmount, ?OrderLineItemEntity $orderLineItemEntity = null, ?OrderDeliveryEntity $orderDeliveryEntity = null): array
    {
        $id = $orderId . 'custom-amount';
        $label = $totalAmount;

        $taxCollection = new CalculatedTaxCollection();
        $taxRuleCollection = new TaxRuleCollection();

        if ($orderLineItemEntity !== null) {
            $id = $orderLineItemEntity->getId();
            $label = $orderLineItemEntity->getLabel();
            $price = $orderLineItemEntity->getPrice();
            if ($price !== null) {
                $taxCollection = $price->getCalculatedTaxes();
                $taxRuleCollection = $price->getTaxRules();
            }
        }
        if ($orderDeliveryEntity !== null) {
            $id = $orderDeliveryEntity->getId();
            $shippingMethod = $orderDeliveryEntity->getShippingMethod();
            $label = 'Shipping';
            if ($shippingMethod !== null) {
                $label = $shippingMethod->getName();
            }
            $price = $orderDeliveryEntity->getShippingCosts();

            $taxCollection = $price->getCalculatedTaxes();
            $taxRuleCollection = $price->getTaxRules();
        }

        $label = trim(sprintf('%s%s%s', $this->prefix, $label, $this->suffix));


        return [
            'id' => Uuid::fromBytesToHex(md5($id, true)), #@todo remove once 6.4 reached end of life
            'identifier' => Uuid::fromBytesToHex(md5($id, true)), #@todo remove once 6.4 reached end of life
            'quantity' => $quantity,
            'label' => $label,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'price' => new CalculatedPrice($unitPrice, $totalAmount, $taxCollection, $taxRuleCollection),
            'customFields' => [
                CustomFieldsInterface::MOLLIE_KEY => [
                    'type' => 'refund',
                    'refundId' => $refundId
                ],
            ],
        ];
    }

    public function createCreditNotes(OrderEntity $order, Refund $refund, RefundRequest $refundRequest, Context $context): void
    {
        if (! $this->enabled) {
            $this->logger->debug('Credit note creation is disabled');
            return;
        }
        $orderId = $order->getId();
        $refundId = $refund->id;

        $lineItems = [];


        $orderLineItems = $order->getLineItems();
        $orderDeliveries = $order->getDeliveries();
        $refundAmount = $refundRequest->getAmount();

        if (count($refundRequest->getItems()) > 0) {
            foreach ($refundRequest->getItems() as $refundLineItem) {
                $orderLineItemId = $refundLineItem->getLineId();
                $totalAmount = $refundLineItem->getAmount();
                $quantity = max(1, $refundLineItem->getQuantity());
                if ($totalAmount <= 0.0) {
                    continue;
                }
                $unitPrice = $totalAmount / $quantity;

                $refundAmount -= $totalAmount;
                $refundAmount = round($refundAmount, 2);

                if ($orderLineItems === null) {
                    continue;
                }

                $filteredOrderLineItems = $orderLineItems->filter(function (OrderLineItemEntity $item) use ($orderLineItemId) {
                    return $item->getId() === $orderLineItemId;
                });

                if ($filteredOrderLineItems->count() === 0) {
                    if ($orderDeliveries === null) {
                        continue;
                    }
                    
                    $filteredOrderDeliveries = $orderDeliveries->filter(function (OrderDeliveryEntity $item) use ($orderLineItemId) {
                        return $item->getId() === $orderLineItemId;
                    });
                    if ($filteredOrderDeliveries->count() === 0) {
                        continue;
                    }

                    $orderDelivery = $filteredOrderDeliveries->first();
                    $lineItems[] = $this->getLineItemArray($orderId, $refundId, $unitPrice, $quantity, $totalAmount, null, $orderDelivery);
                    continue;
                }
                $orderLineItem = $filteredOrderLineItems->first();

                $lineItems[] = $this->getLineItemArray($orderId, $refundId, $unitPrice, $quantity, $totalAmount, $orderLineItem);
            }
        }

        if ($refundAmount > 0) {
            $lineItems[] = $this->getLineItemArray($orderId, $refundId, $refundAmount, 1, $refundAmount);
        }

        $this->logger->debug('Adding credit note to order', ['orderId' => $orderId, 'refundId' => $refundId, 'lineItems' => $lineItems]);
        $this->orderRepository->upsert([[
            'id' => $orderId,
            'lineItems' => $lineItems,
        ]], $context);
    }

    public function cancelCreditNotes(string $orderId, Context $context): void
    {

        //do not check for enabled, because credit notes could be created before and you still want to delete them, even if the feature is disabled now

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('type', LineItem::CREDIT_LINE_ITEM_TYPE));
        $this->logger->debug('Start cancel credit notes', ['orderId' => $orderId]);
        $searchResult = $this->orderLineItemRepository->searchIds($criteria, $context);
        if ($searchResult->getTotal() === 0) {
            $this->logger->debug('No credit notes found', ['orderId' => $orderId]);
            return;
        }
        $ids = $searchResult->getIds();
        $toDelete = [];
        foreach ($ids as $id) {
            $toDelete[] = ['id' => $id];
        }
        $this->orderLineItemRepository->delete($toDelete, $context);

        $this->logger->debug('Deleted credit notes from order', ['orderId' => $orderId, 'total' => count($ids)]);
    }
}
