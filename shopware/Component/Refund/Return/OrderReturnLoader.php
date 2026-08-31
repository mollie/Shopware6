<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return;

use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnLineItemStruct;
use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Psr\Log\LoggerInterface;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturn\OrderReturnEntity;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The only class in the plugin that touches the Return Management of SwagCommercial. It reads the
 * return and maps it onto OrderReturnStruct, so nothing behind it needs the paid plugin.
 *
 * SwagCommercial is not in the plugin's own vendor/, which is what unit tests bootstrap from, so
 * this class cannot be unit tested and is excluded from the coverage report in config/phpunit.xml.
 * Keep it a mapper: anything with a decision in it belongs in one of the actions next to it,
 * where it can be covered.
 */
final class OrderReturnLoader implements OrderReturnLoaderInterface
{
    /**
     * @param null|EntityRepository<EntityCollection<OrderReturnEntity>> $orderReturnRepository
     */
    public function __construct(
        #[Autowire(service: 'order_return.repository')]
        private readonly ?EntityRepository $orderReturnRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->orderReturnRepository !== null;
    }

    public function load(string $returnId, Context $context): ?OrderReturnStruct
    {
        if ($this->orderReturnRepository === null) {
            return null;
        }

        $criteria = new Criteria([$returnId]);
        $criteria->addAssociation('state');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.transactions.stateMachineState');

        $result = $this->orderReturnRepository->search($criteria, $context);

        if ($result->getTotal() === 0) {
            $this->logger->warning('OrderReturn - Return not found', ['returnId' => $returnId]);

            return null;
        }

        $orderReturn = $result->first();

        if (! $orderReturn instanceof OrderReturnEntity) {
            $this->logger->warning('OrderReturn - Return not found', ['returnId' => $returnId]);

            return null;
        }

        return $this->map($orderReturn);
    }

    private function map(OrderReturnEntity $orderReturn): OrderReturnStruct
    {
        $lineItems = [];

        /** @var OrderReturnLineItemEntity $lineItem */
        foreach ($orderReturn->getLineItems() as $lineItem) {
            $lineItems[] = new OrderReturnLineItemStruct(
                (string) $lineItem->getOrderLineItemId(),
                $lineItem->getQuantity(),
                $lineItem->getRefundAmount(),
                $lineItem->getRestockQuantity()
            );
        }

        $shippingCosts = $orderReturn->getShippingCosts();

        return new OrderReturnStruct(
            $orderReturn->getId(),
            $orderReturn->getState()?->getTechnicalName(),
            $orderReturn->getOrder(),
            $orderReturn->getAmountTotal(),
            (string) $orderReturn->getInternalComment(),
            $shippingCosts instanceof CalculatedPrice ? $shippingCosts->getTotalPrice() : 0.0,
            $lineItems
        );
    }
}
