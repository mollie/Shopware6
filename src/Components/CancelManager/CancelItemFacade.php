<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\CancelManager;

use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
use Kiener\MolliePayments\Event\OrderLinesUpdatedEvent;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
class CancelItemFacade
{
    private MollieApiFactory $clientFactory;
    private LoggerInterface $logger;
    /** @var EntityRepository */
    private $orderLineItemRepository;
    private StockManagerInterface $stockManager;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @param EntityRepository $orderLineItemRepository
     */
    public function __construct(MollieApiFactory $clientFactory, $orderLineItemRepository, StockManagerInterface $stockManager, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->stockManager = $stockManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function cancelItem(string $mollieOrderId, string $mollieLineId, string $shopwareLineId, int $quantity, bool $resetStock, Context $context): CancelItemResponse
    {
        $response = new CancelItemResponse();
        $logArguments = ['mollieOrderId' => $mollieOrderId, 'mollieLineId' => $mollieLineId, 'shopwareLineId' => $shopwareLineId, 'quantity' => $quantity, 'resetStock' => (string) $resetStock];
        try {
            $this->logger->info('Initiated cancelling an item', $logArguments);

            if ($quantity === 0) {
                $this->logger->error('Cancelling item failed, quantity is 0', $logArguments);

                return $response->failedWithMessage('quantityZero');
            }

            $criteria = new Criteria([$shopwareLineId]);
            $criteria->addAssociation('order');
            $searchResult = $this->orderLineItemRepository->search($criteria, $context);
            if ($searchResult->count() === 0) {
                $this->logger->error('Cancelling item failed, shopware line item not found', $logArguments);

                return $response->failedWithMessage('invalidShopwareLineId');
            }
            /** @var OrderLineItemEntity $shopwareLineItem */
            $shopwareLineItem = $searchResult->first();

            /** @var OrderEntity $shopwareOrder */
            $shopwareOrder = $shopwareLineItem->getOrder();

            $client = $this->clientFactory->getClient($shopwareOrder->getSalesChannelId());

            $mollieOrder = $client->orders->get($mollieOrderId);

            $orderLine = $mollieOrder->lines()->get($mollieLineId);

            if ($orderLine === null) {
                $this->logger->error('Cancelling item failed, lineItem does not exists in order', $logArguments);

                return $response->failedWithMessage('invalidLine');
            }
            if ($quantity > $orderLine->cancelableQuantity) {
                $logArguments['cancelableQuantity'] = $orderLine->cancelableQuantity;

                $this->logger->error('Cancelling item failed, cancelableQuantity is too high', $logArguments);

                return $response->failedWithMessage('quantityTooHigh');
            }

            // First we reset the stocks, just in case something went wrong the customer still have the chance to cancel the item on mollie page
            if ($resetStock) {
                $this->logger->info('Start to reset stocks', $logArguments);

                $this->stockManager->increaseStock($shopwareLineItem, $quantity);

                $this->logger->info('Stock rested', $logArguments);
            }

            $lines = [
                'id' => $orderLine->id,
                'quantity' => $quantity,
            ];

            $mollieOrder->cancelLines(['lines' => [$lines]]);
            $this->logger->info('Item cancelled successful', ['orderId' => $mollieOrderId, 'mollieLineId' => $mollieLineId, 'quantity' => $quantity]);

            $this->eventDispatcher->dispatch(new OrderLinesUpdatedEvent($mollieOrder));

            $response = $response->withData($lines);
        } catch (\Throwable $e) {
            $response = $response->failedWithMessage($e->getMessage());
        }

        return $response;
    }
}
