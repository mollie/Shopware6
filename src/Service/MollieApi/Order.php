<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;


use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Psr\Log\LoggerInterface;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
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
