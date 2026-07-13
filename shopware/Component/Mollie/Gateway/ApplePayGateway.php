<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

final class ApplePayGateway implements ApplePayGatewayInterface
{
    use ExceptionTrait;

    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Mollie\Shopware\Component\Mollie\Exception\ApiException
     *
     * @return mixed[]
     */
    public function requestSession(string $domain, string $validationUrl, string $salesChannelId): array
    {
        try {
            $client = $this->clientFactory->create($salesChannelId, true);
            $response = $client->post('wallets/applepay/sessions', [
                'form_params' => [
                    'validationUrl' => $validationUrl,
                    'domain' => $this->extractHost($domain),
                ]
            ]);

            $this->logger->debug('Requested apple pay session');

            return json_decode($response->getBody()->getContents(),true);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    private function extractHost(string $domain): string
    {
        $domain = trim($domain);
        if (preg_match('#^https?://#i', $domain) !== 1) {
            $domain = 'https://' . $domain;
        }

        $host = parse_url($domain, PHP_URL_HOST);

        return is_string($host) ? $host : $domain;
    }
}
