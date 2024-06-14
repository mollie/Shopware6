<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\CancelManager;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\MollieApiClient;
use Psr\Log\LoggerInterface;

/**
 * @final
 */
class CancelItemFacade
{
    private MollieApiClient $client;


    private LoggerInterface $logger;

    public function __construct(MollieApiFactory $clientFactory, LoggerInterface $logger)
    {
        $this->client = $clientFactory->getClient();
        $this->logger = $logger;
    }

    public function cancelItem(string $orderId, string $mollieLineId, int $quantity, bool $resetStock): CancelItemResponse
    {
        $response = new CancelItemResponse();
        $logArguments = ['orderId' => $orderId, 'mollieLineId' => $mollieLineId, 'quantity' => $quantity];
        try {
            $this->logger->debug('Initiated cancelling an item', $logArguments);

            if ($quantity === 0) {
                $this->logger->error('Cancelling item failed, quantity is 0', $logArguments);
                return $response->failedWithMessage('Quantity is empty');
            }

            $mollieOrder = $this->client->orders->get($orderId);

            $orderLine = $mollieOrder->lines()->get($mollieLineId);

            if ($orderLine === null) {
                $this->logger->error('Cancelling item failed, lineItem does not exists in order', $logArguments);
                return $response->failedWithMessage(sprintf('Line ID %s does not exists in order %s', $mollieLineId, $orderId));
            }
            if ($quantity > $orderLine->cancelableQuantity) {
                $this->logger->error('Cancelling item failed, cancelableQuantity is too high', $logArguments);
                return $response->failedWithMessage(sprintf('Quantity too high, you can cancel up to %d items', $orderLine->cancelableQuantity));
            }

            $lines = [
                'id' => $orderLine->id,
                'quantity' => $quantity
            ];

            $mollieOrder->cancelLines(['lines' => [$lines]]);
            $this->logger->info('Item cancelled successful', ['orderId' => $orderId, 'mollieLineId' => $mollieLineId, 'quantity' => $quantity]);

            $response = $response->withData($lines);
        } catch (\Throwable $e) {
            $response = $response->failedWithMessage($e->getMessage());
        }

        return $response;
    }
}
