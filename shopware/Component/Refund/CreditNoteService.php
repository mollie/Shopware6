<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
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
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<OrderLineItemCollection> $orderLineItemRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: 'order_line_item.repository')]
        private readonly EntityRepository $orderLineItemRepository,
        private readonly RecalculationService $recalculationService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function addCreditNote(OrderEntity $order, MollieRefund $refund, RefundSettings $settings, Context $context): void
    {
        $orderId = $order->getId();
        $grossAmount = (float) $refund->getAmount()->getValue();

        if ($grossAmount <= 0.0) {
            return;
        }

        // Mollie amounts are always gross. For net orders AbsolutePriceDefinition treats its
        // value as net and adds VAT on top, which would inflate the credit note total beyond
        // the actual refund amount. Scale down to the net equivalent using the order ratio.
        $priceAmount = $grossAmount;
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_NET && $order->getAmountTotal() > 0.0) {
            $priceAmount = $grossAmount * ($order->getAmountNet() / $order->getAmountTotal());
        }

        $description = $refund->getDescription();
        $label = $settings->getCreditNoteLabel($description ?: 'Refund');

        $identifier = Uuid::fromStringToHex($orderId . $refund->getId());
        $creditLineItem = new ShopwareLineItem($identifier, ShopwareLineItem::CREDIT_LINE_ITEM_TYPE, null, 1);
        $creditLineItem->setLabel($label);
        $creditLineItem->setRemovable(true);
        $creditLineItem->setStackable(false);

        $filterRule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, ShopwareLineItem::CREDIT_LINE_ITEM_TYPE);
        $priceDefinition = new AbsolutePriceDefinition(-$priceAmount, $filterRule);
        $creditLineItem->setPriceDefinition($priceDefinition);

        $this->logger->debug('Adding credit note to order', [
            'orderId' => $orderId,
            'mollieRefundId' => $refund->getId(),
            'grossAmount' => $grossAmount,
            'priceAmount' => $priceAmount,
        ]);

        $versionId = $this->orderRepository->createVersion($orderId, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->recalculationService->addCustomLineItem($orderId, $creditLineItem, $versionContext);
        $context->scope(Context::SYSTEM_SCOPE, function (Context $systemContext) use ($versionId): void {
            $this->orderRepository->merge($versionId, $systemContext);
        });
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

        $toDelete = [];
        $ids = $lineItems->getIds();

        foreach ($ids as $id) {
            $toDelete[] = ['id' => $id];
        }

        $this->orderLineItemRepository->delete($toDelete, $context);

        $versionId = $this->orderRepository->createVersion($orderId, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->recalculationService->recalculateOrder($orderId, $versionContext);
        $context->scope(Context::SYSTEM_SCOPE, function (Context $systemContext) use ($versionId): void {
            $this->orderRepository->merge($versionId, $systemContext);
        });

        $this->logger->debug('Cancelled credit note from order', [
            'orderId' => $orderId,
            'mollieRefundId' => $mollieRefundId,
        ]);
    }
}
