<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreateOrderRefund;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Refund;
use Mollie\Shopware\Component\Mollie\RefundCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RefundGateway implements RefundGatewayInterface
{
    use ExceptionTrait;

    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function createRefund(CreateRefund $createRefund, string $orderNumber, string $salesChannelId): Refund
    {
        if ($createRefund instanceof CreateOrderRefund) {
            return $this->createOrderRefund($createRefund, $orderNumber, $salesChannelId);
        }

        if ($createRefund instanceof CreatePaymentRefund) {
            return $this->createPaymentRefund($createRefund, $orderNumber, $salesChannelId);
        }

        throw new \LogicException('Unknown refund type: ' . get_class($createRefund));
    }

    public function cancelRefund(string $paymentId, string $refundId, string $orderNumber, string $salesChannelId): void
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $client->delete('payments/' . $paymentId . '/refunds/' . $refundId);

            $this->logger->info('Mollie refund cancelled', [
                'paymentId' => $paymentId,
                'refundId' => $refundId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function listRefunds(string $paymentId, string $orderNumber, string $salesChannelId): RefundCollection
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('payments/' . $paymentId . '/refunds');
            $body = json_decode($response->getBody()->getContents(), true);

            $refundsData = $body['_embedded']['refunds'] ?? [];
            $collection = new RefundCollection();
            foreach ($refundsData as $refundData) {
                $collection->add(Refund::createFromClientResponse($refundData));
            }

            $this->logger->debug('Mollie refunds loaded', [
                'paymentId' => $paymentId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'refundCount' => count($refundsData),
            ]);

            return $collection;
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    private function createPaymentRefund(CreatePaymentRefund $createRefund, string $orderNumber, string $salesChannelId): Refund
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $createRefund->toArray();

            $this->logger->info('Mollie refund create requested', [
                'paymentId' => $createRefund->getPaymentId(),
                'orderNumber' => $orderNumber,
                'requestParameter' => $formParams,
                'salesChannelId' => $salesChannelId,
            ]);

            $response = $client->post('payments/' . $createRefund->getPaymentId() . '/refunds', [
                'form_params' => $formParams,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Mollie refund created', [
                'paymentId' => $createRefund->getPaymentId(),
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return Refund::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    private function createOrderRefund(CreateOrderRefund $createRefund, string $orderNumber, string $salesChannelId): Refund
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $createRefund->toArray();

            $this->logger->info('Mollie order refund create requested', [
                'orderId' => $createRefund->getOrderId(),
                'orderNumber' => $orderNumber,
                'requestParameter' => $formParams,
                'salesChannelId' => $salesChannelId,
            ]);

            $response = $client->post('orders/' . $createRefund->getOrderId() . '/refunds', [
                'json' => $formParams,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Mollie order refund created', [
                'orderId' => $createRefund->getOrderId(),
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return Refund::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }
}
