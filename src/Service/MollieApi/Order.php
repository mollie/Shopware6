<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Factory\MollieApiFactory;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeCancelledException;
use Kiener\MolliePayments\Service\LoggerService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Order
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    /**
     * @var LoggerService
     */
    private $logger;

    public function __construct(MollieApiFactory $clientFactory, LoggerService $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
    }

    public function getOrder(string $mollieOrderId, SalesChannelContext $salesChannelContext): ?MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($salesChannelContext->getSalesChannelId());

        try {
            $mollieOrder = $apiClient->orders->get($mollieOrderId);
        } catch (ApiException $e) {
            $this->logger->error(
                sprintf(
                    'API error occured when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                ),
                $e->getTrace()
            );

            return null;
        }

        return $mollieOrder;
    }

    public function createOrder(array $orderData, string $orderSalesChannelContextId, SalesChannelContext $salesChannelContext): MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($orderSalesChannelContextId);

        /**
         * Create an order at Mollie based on the prepared
         * array of order data.
         *
         * @throws ApiException
         * @var \Mollie\Api\Resources\Order $mollieOrder
         */
        try {
            return $apiClient->orders->create($orderData);
        } catch (ApiException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $salesChannelContext->getContext(),
                $e,
                [
                    'function' => 'finalize-payment',
                ],
                Logger::CRITICAL
            );

            throw new RuntimeException(sprintf('Could not create Mollie order, error: %s', $e->getMessage()));
        }
    }

    public function cancelOrder(string $mollieOrderId, SalesChannelContext $salesChannelContext): void
    {
        $mollieOrder = $this->getOrder($mollieOrderId, $salesChannelContext);

        if (!$mollieOrder instanceof MollieOrder) {
            throw new MollieOrderCouldNotBeCancelledException($mollieOrderId);
        }

        try {
            $mollieOrder->cancel();
        } catch (ApiException $e) {
            throw new MollieOrderCouldNotBeCancelledException($mollieOrderId, [], $e);
        }
    }

    public function setShipment(string $mollieOrderId, SalesChannelContext $salesChannelContext): bool
    {
        $mollieOrder = $this->getOrder($mollieOrderId, $salesChannelContext);

        if (!$mollieOrder instanceof Order) {
            return false;
        }

        $shouldCreateShipment = false;

        foreach ($mollieOrder->lines as $line) {
            if ($line->shippableQuantity > 0) {
                $shouldCreateShipment = true;
                break;
            }
        }

        if ($shouldCreateShipment) {
            $mollieOrder->shipAll();

            return true;
        }

        return false;
    }
}
