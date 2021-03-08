<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
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

    public function setShipment(string $mollieOrderId, string $salesChannelId): bool
    {
        $apiClient = $this->clientFactory->getClient($salesChannelId);

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

            return false;
        }

        $shouldCreateShipment = false;

        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            if (!$orderLine instanceof OrderLine) {
                continue;
            }

            if ($orderLine->shippableQuantity > 0) {
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
