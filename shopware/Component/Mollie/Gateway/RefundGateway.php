<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
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

    public function createRefund(CreateRefund $createRefund, string $salesChannelId): Refund
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $createRefund->toArray();

            $response = $client->post('payments/' . $createRefund->getPaymentId() . '/refunds', [
                'form_params' => $formParams,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Mollie refund created', [
                'paymentId' => $createRefund->getPaymentId(),
                'requestParameter' => $formParams,
                'responseParameter' => $body,
                'salesChannelId' => $salesChannelId,
            ]);

            return Refund::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function cancelRefund(string $paymentId, string $refundId, string $salesChannelId): void
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $client->delete('payments/' . $paymentId . '/refunds/' . $refundId);

            $this->logger->info('Mollie refund cancelled', [
                'paymentId' => $paymentId,
                'refundId' => $refundId,
                'salesChannelId' => $salesChannelId,
            ]);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function listRefunds(string $paymentId, string $salesChannelId): RefundCollection
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('payments/' . $paymentId . '/refunds');
            $body = json_decode($response->getBody()->getContents(), true);

            $collection = new RefundCollection();
            foreach ($body['_embedded']['refunds'] ?? [] as $refundData) {
                $collection->add(Refund::createFromClientResponse($refundData));
            }

            return $collection;
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }
}
