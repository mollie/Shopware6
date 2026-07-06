<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentLink;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentLinkGateway implements PaymentLinkGatewayInterface
{
    use ExceptionTrait;

    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function createPaymentLink(CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $createPaymentLink->toArray();

            $this->logger->info('Mollie payment link create requested', [
                'orderNumber' => $orderNumber,
                'requestParameter' => $formParams,
                'salesChannelId' => $salesChannelId,
            ]);

            $response = $client->post('payment-links', [
                'json' => $formParams,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            $paymentLink = PaymentLink::createFromClientResponse($body);

            $this->logger->info('Mollie payment link created', [
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'paymentLinkId' => $paymentLink->getId(),
            ]);

            return $paymentLink;
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function listPaymentLinkPayments(string $paymentLinkId, string $orderNumber, string $salesChannelId): PaymentCollection
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('payment-links/' . $paymentLinkId . '/payments');
            $body = json_decode($response->getBody()->getContents(), true);

            $paymentsData = $body['_embedded']['payments'] ?? [];
            $collection = new PaymentCollection();
            foreach ($paymentsData as $paymentData) {
                $payment = Payment::createFromClientResponse($paymentData);
                $collection->set($payment->getId(), $payment);
            }

            $this->logger->debug('Mollie payment link payments loaded', [
                'paymentLinkId' => $paymentLinkId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'paymentCount' => $collection->count(),
            ]);

            return $collection;
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }
}
