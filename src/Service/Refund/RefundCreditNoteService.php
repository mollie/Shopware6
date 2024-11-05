<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Service\Refund\Exceptions\CreditNoteException;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class RefundCreditNoteService
{
    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @var EntityRepository
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
     * @var RefundSummarizationService
     */
    private $refundSummarizationService;

    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $orderLineItemRepository,
        SettingsService  $settingsService,
        RefundSummarizationService $refundSummarizationService,
        LoggerInterface  $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $settings = $settingsService->getSettings();
        $this->enabled = $settings->isRefundManagerCreateCreditNotesEnabled();
        $this->prefix = $settings->getRefundManagerCreateCreditNotesPrefix();
        $this->suffix = $settings->getRefundManagerCreateCreditNotesSuffix();
        $this->logger = $logger;
        $this->refundSummarizationService = $refundSummarizationService;
    }

    /**
     * @param array<int|string, array{id: string}> $lineItems
     * @throws CreditNoteException
     */
    public function addCreditNoteToOrder(string $orderId, string $refundId, array $lineItems, Context $context): void
    {
        if (!$this->enabled) {
            $this->logger->debug('Credit note creation is disabled');
            return;
        }

        if (empty($orderId) || empty($refundId)) {
            throw CreditNoteException::forAddingLineItems(sprintf('OrderId or RefundId is empty. OrderID: %s RefundID: %s', $orderId, $refundId));
        }

        if (empty($lineItems)) {
            throw CreditNoteException::forAddingLineItems(sprintf('No line items found for credit note. OrderID: %s RefundID: %s', $orderId, $refundId));
        }

        $data = ['id' => $orderId, 'lineItems' => []];

        foreach ($lineItems as ['id' => $lineItemId, 'amount' => $amount]) {
            if ($amount === 0) {
                // refund manager front end sends all line items, even if they are not going to be refunded
                continue;
            }
            $lineItem = $this->orderLineItemRepository->search(new Criteria([$lineItemId]), $context)->first();
            if (!$lineItem instanceof OrderLineItemEntity) {
                continue;
            }
            $price = $lineItem->getPrice();
            if (!$price instanceof CalculatedPrice) {
                continue;
            }
            $taxRules = $price->getTaxRules();
            $totalPrice = $lineItem->getTotalPrice();
            $quantity = $lineItem->getQuantity();
            if ($totalPrice <= 0 || $quantity <= 0) {
                continue;
            }
            $unitPrice = round($totalPrice / $quantity, 2);
            $totalPrice *= -1;
            $unitPrice *= -1;
            $data['lineItems'][] = [
                'id' => Uuid::fromBytesToHex(md5($lineItemId, true)), #@todo remove once 6.4 reached end of life
                'identifier' => Uuid::fromBytesToHex(md5($lineItem->getIdentifier(), true)),#@todo remove once 6.4 reached end of life
                'quantity' => $quantity,
                'label' => sprintf('%s%s%s', $this->prefix, $lineItem->getLabel(), $this->suffix),
                'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                'price' => new CalculatedPrice($unitPrice, $totalPrice, new CalculatedTaxCollection(), $taxRules),
                'priceDefinition' => new QuantityPriceDefinition($totalPrice, $taxRules, $quantity),
                'customFields' => [
                    'mollie_payments' => [
                        'type' => 'refund',
                        'refundId' => $refundId,
                        'lineItemId' => $lineItemId
                    ],
                ],
            ];
        }

        if (empty($data['lineItems'])) {
            throw CreditNoteException::forAddingLineItems(sprintf('No credit note line items found for order. OrderID: %s RefundID: %s', $orderId, $refundId), CreditNoteException::CODE_WARNING_LEVEL);
        }

        $this->logger->debug('Adding credit note to order', ['orderId' => $orderId, 'refundId' => $refundId, 'lineItems' => $data['lineItems']]);
        $this->orderRepository->upsert([$data], $context);
    }

    /**
     * @throws CreditNoteException
     */
    public function cancelCreditNoteToOrder(string $orderId, string $refundId, Context $context): void
    {
        if (empty($orderId) || empty($refundId)) {
            throw CreditNoteException::forRemovingLineItems(sprintf('OrderId or RefundId is empty. OrderID: %s RefundID: %s', $orderId, $refundId));
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $searchResult = $this->orderRepository->search($criteria, $context);
        $order = $searchResult->first();

        if (!$order instanceof OrderEntity) {
            throw CreditNoteException::forRemovingLineItems(sprintf('Order not found. OrderID: %s RefundID: %s', $orderId, $refundId));
        }

        $lineItems = $order->getLineItems();

        if ($lineItems === null) {
            throw CreditNoteException::forRemovingLineItems(
                sprintf('No line items found for order. OrderID: %s RefundID: %s', $orderId, $refundId),
                CreditNoteException::CODE_WARNING_LEVEL
            );
        }

        $ids = [];
        foreach ($lineItems as $lineItem) {
            /** @var OrderLineItemEntity $lineItem */
            $customFields = $lineItem->getCustomFields();
            if (!isset($customFields['mollie_payments'], $customFields['mollie_payments']['type']) || $customFields['mollie_payments']['type'] !== 'refund') {
                continue;
            }

            $lineItemRefundId = $customFields['mollie_payments']['refundId'];
            if ($lineItemRefundId !== $refundId) {
                continue;
            }

            $ids[] = ['id' => $lineItem->getId()];
        }

        if (empty($ids)) {
            throw CreditNoteException::forRemovingLineItems(
                sprintf('No credit note line items found for order. OrderID: %s RefundID: %s', $orderId, $refundId),
                CreditNoteException::CODE_WARNING_LEVEL
            );
        }

        $this->orderLineItemRepository->delete($ids, $context);
    }

    /**
     * Checks if the provided line items contain any refunded amounts.
     *
     * This method evaluates the total refund amount of the given line items by
     * using the `getLineItemsRefundSum` method from `RefundSummarizationService`.
     * If the summed refund amount is greater than zero, it indicates that
     * the items contain refunded line items.
     *
     * @param array<int|string, mixed> $items Array of items, each potentially containing an 'amount' field.
     * @return bool True if the items contain refunded line items (sum of 'amount' > 0), false otherwise.
     */
    public function containsRefundedLineItems(array $items): bool
    {
        // Checks if the total refund sum of line items is greater than zero,
        // which indicates that there are refunded line items present.
        return $this->refundSummarizationService->getLineItemsRefundSum($items) > 0;
    }
}
