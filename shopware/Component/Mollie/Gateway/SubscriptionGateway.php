<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionGateway implements SubscriptionGatewayInterface
{
    use ExceptionTrait;

    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function createSubscription(CreateSubscription $createSubscription,string $customerId,string $orderNumber, string $salesChannelId): Subscription
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);

            $formParams = $createSubscription->toArray();

            $response = $client->post('customers/' . $customerId . '/subscriptions', [
                'form_params' => $formParams,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);
            $this->logger->info('Subscription created', [
                'requestParameter' => $formParams,
                'responseParameter' => $body,
                'customerId' => $customerId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return Subscription::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }
}
