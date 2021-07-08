<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Psr\Log\LoggerInterface;

class Order
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(MollieApiFactory $clientFactory, LoggerInterface $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
    }

    public function getMollieOrder(string $mollieOrderId, ?string $salesChannelId): MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($salesChannelId);

        try {
            return $apiClient->orders->get($mollieOrderId);
        } catch (ApiException $e) {
            $this->logger->error(
                sprintf(
                    'API error occured when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                )
            );

            throw $e;
        }
    }

    public function getPaymentUrl(string $mollieOrderId, string $salesChannelId): ?string
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        return $mollieOrder->status === 'created' ? $mollieOrder->getCheckoutUrl() : null;
    }

    public function setShipment(string $mollieOrderId, ?string $salesChannelId): bool
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            if ($orderLine->shippableQuantity > 0) {
                $mollieOrder->shipAll();

                return true;
            }
        }

        return false;
    }
}
