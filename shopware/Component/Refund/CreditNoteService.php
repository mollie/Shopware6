<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CreditNoteService
{
    /**
     * @param EntityRepository<OrderLineItemCollection> $orderLineItemRepository
     */
    public function __construct(
        #[Autowire(service: 'order_line_item.repository')]
        private readonly EntityRepository $orderLineItemRepository,
        private readonly RecalculationService $recalculationService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function addCreditNote(string $orderId, MollieRefund $refund, RefundSettings $settings, Context $context): void
    {
        $totalAmount = (float) $refund->getAmount()->getValue();

        if ($totalAmount <= 0.0) {
            return;
        }

        $description = $refund->getDescription();
        $label = $settings->getCreditNoteLabel($description ?: 'Refund');

        $identifier = Uuid::fromStringToHex($orderId . $refund->getId());
        $creditLineItem = new ShopwareLineItem($identifier, ShopwareLineItem::CREDIT_LINE_ITEM_TYPE, null, 1);
        $creditLineItem->setLabel($label);
        $creditLineItem->setRemovable(true);
        $creditLineItem->setStackable(false);

        $filterRule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, ShopwareLineItem::CREDIT_LINE_ITEM_TYPE);
        $priceDefinition = new AbsolutePriceDefinition(-$totalAmount, $filterRule);
        $creditLineItem->setPriceDefinition($priceDefinition);

        $this->logger->debug('Adding credit note to order', [
            'orderId' => $orderId,
            'mollieRefundId' => $refund->getId(),
            'amount' => $totalAmount,
        ]);

        $this->recalculationService->addCustomLineItem($orderId, $creditLineItem, $context);
    }

    public function cancelCreditNote(string $orderId, string $mollieRefundId, Context $context): void
    {
        $creditNoteIdentifier = Uuid::fromStringToHex($orderId . $mollieRefundId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('identifier', $creditNoteIdentifier));

        $lineItems = $this->orderLineItemRepository->searchIds($criteria, $context);

        if ($lineItems->getTotal() === 0) {
            $this->logger->debug('No matching credit note found to cancel', [
                'orderId' => $orderId,
                'mollieRefundId' => $mollieRefundId,
            ]);

            return;
        }

        $toDelete = array_map(function (string $id) {
            return ['id' => $id];
        }, $lineItems->getIds());

        $this->orderLineItemRepository->delete($toDelete, $context);

        $this->recalculationService->recalculateOrder($orderId, $context);

        $this->logger->debug('Cancelled credit note from order', [
            'orderId' => $orderId,
            'mollieRefundId' => $mollieRefundId,
        ]);
    }
}
