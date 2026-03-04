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

    public function createSubscription(CreateSubscription $createSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription
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
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function getSubscription(string $mollieSubscriptionId, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);

            $response = $client->get('customers/' . $customerId . '/subscriptions/' . $mollieSubscriptionId);
            $body = json_decode($response->getBody()->getContents(), true);
            $this->logger->info('Subscription loaded from mollie api', [
                'responseParameter' => $body,
                'customerId' => $customerId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
                'subscriptionId' => $mollieSubscriptionId
            ]);

            return Subscription::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function copySubscription(Subscription $mollieSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        try {
            $createSubscription = new CreateSubscription($mollieSubscription->getDescription(), $mollieSubscription->getInterval(), $mollieSubscription->getAmount());

            $createSubscription->setStartDate($mollieSubscription->getStartDate()->format('Y-m-d'));
            $createSubscription->setWebhookUrl($mollieSubscription->getWebhookUrl());
            $createSubscription->setMetadata($mollieSubscription->getMetadata());
            $createSubscription->setMandateId($mollieSubscription->getMandateId());

            if ($mollieSubscription->getTimesRemaining() !== null) {
                $createSubscription->setTimes($mollieSubscription->getTimesRemaining());
            }
            $this->logger->info('Subscription copied', [
                'requestParameter' => $createSubscription->toArray(),
                'customerId' => $customerId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return $this->createSubscription($createSubscription, $customerId, $orderNumber, $salesChannelId);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function cancelSubscription(string $mollieSubscriptionId, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);

            $response = $client->delete('customers/' . $customerId . '/subscriptions/' . $mollieSubscriptionId);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Subscription cancelled over mollie api', [
                'responseParameter' => $body,
                'customerId' => $customerId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return Subscription::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }

    public function updateSubscription(Subscription $mollieSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);

            $mollieSubscriptionId = $mollieSubscription->getId();

            $response = $client->patch('customers/' . $customerId . '/subscriptions/' . $mollieSubscriptionId, [
                'form_params' => $mollieSubscription->toArray()
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $this->logger->info('Subscription updated over mollie api', [
                'responseParameter' => $body,
                'customerId' => $customerId,
                'orderNumber' => $orderNumber,
                'salesChannelId' => $salesChannelId,
            ]);

            return Subscription::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception, $orderNumber);
        }
    }
}
