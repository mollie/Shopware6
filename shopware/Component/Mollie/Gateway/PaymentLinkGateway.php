<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentHydrator;
use Mollie\Shopware\Component\Mollie\PaymentLink;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentLinkGateway implements PaymentLinkGatewayInterface
{
    use ExceptionTrait;

    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: PaymentHydrator::class)]
        private PaymentHydrator $paymentHydrator,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function createPaymentLink(CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $formParams = $createPaymentLink->toArray();

            $response = $client->post('payment-links', [
                'form_params' => $formParams,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Mollie payment link created', [
                'requestParameter' => $formParams,
                'responseParameter' => $body,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return PaymentLink::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function updatePaymentLink(string $paymentLinkId, CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);

            // The amount is immutable once the link exists, so it must not be sent on update.
            $formParams = $createPaymentLink->toArray();
            unset($formParams['amount']);

            $response = $client->patch('payment-links/' . $paymentLinkId, [
                'form_params' => $formParams,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Mollie payment link updated', [
                'paymentLinkId' => $paymentLinkId,
                'requestParameter' => $formParams,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return PaymentLink::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function getPaymentLinkPayments(string $paymentLinkId, string $orderNumber, string $salesChannelId): PaymentCollection
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get(sprintf('payment-links/%s/payments', $paymentLinkId));
            $body = json_decode($response->getBody()->getContents(), true);

            $collection = new PaymentCollection();
            foreach ($body['_embedded']['payments'] ?? [] as $paymentBody) {
                $payment = $this->paymentHydrator->hydrate($paymentBody);
                $collection->set($payment->getId(), $payment);
            }

            $this->logger->debug('Payment link payments loaded from mollie api', [
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
