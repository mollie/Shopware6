<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
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

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param array $parameters
     * @return MollieOrder
     * @throws CouldNotFetchMollieOrderException
     */
    public function getMollieOrder(string $mollieOrderId, string $salesChannelId, array $parameters = []): MollieOrder
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            return $apiClient->orders->get($mollieOrderId, $parameters);
        } catch (ApiException $e) {
            $this->logger->error(
                sprintf(
                    'API error occurred when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                )
            );

            throw new CouldNotFetchMollieOrderException($mollieOrderId, $e);
        }
    }

    public function getPaymentUrl(string $mollieOrderId, string $salesChannelId): ?string
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        return $mollieOrder->status === 'created' ? $mollieOrder->getCheckoutUrl() : null;
    }

    public function setShipment(string $mollieOrderId, string $salesChannelId): bool
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

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return Payment
     * @throws CouldNotFetchMollieOrderException
     * @throws PaymentNotFoundException
     */
    public function getCompletedPayment(string $mollieOrderId, string $salesChannelId): Payment
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId, ['embed' => 'payments']);

        if ($mollieOrder->payments()->count() === 0) {
            throw new PaymentNotFoundException($mollieOrderId);
        }

        /** @var Payment $payment */
        foreach ($mollieOrder->payments()->getArrayCopy() as $payment) {
            if (in_array($payment->status, [
                PaymentStatus::STATUS_PAID,
                PaymentStatus::STATUS_AUTHORIZED // Klarna
            ])) {
                return $payment;
            }
        }

        throw new PaymentNotFoundException($mollieOrderId);
    }
}
