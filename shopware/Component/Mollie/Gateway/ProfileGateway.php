<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Profile;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProfileGateway implements ProfileGatewayInterface
{
    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function getCurrentProfile(string $salesChannelId): Profile
    {
        try {
            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->get('profiles/me');
            $body = json_decode($response->getBody()->getContents(), true);

            return Profile::fromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    private function convertException(ClientException $exception): ApiException
    {
        $body = json_decode($exception->getResponse()->getBody()->getContents(), true);

        $this->logger->error('There was an error from Mollies API', [
            'title' => $body['title'] ?? 'no title',
            'error' => $body['detail'] ?? 'no details',
            'field' => $body['field'] ?? 'no field',
        ]);

        return new ApiException($exception->getCode(), $body['title'] ?? '', $body['detail'] ?? '', $body['field'] ?? '');
    }
}
