<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Service;

use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

final class ApplePayDomainVerificationService
{
    public const URL_FILE = 'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association';

    public const LOCAL_FILE = '/.well-known/apple-developer-merchantid-domain-association';

    public function __construct(
        #[Autowire(service: 'shopware.filesystem.public')]
        private readonly Filesystem $filesystem,
        #[Autowire(service: 'shopware.store_download_client')]
        private readonly Client $httpClient
    ) {
    }

    public function downloadDomainAssociationFile(): bool
    {
        $response = $this->httpClient->get(self::URL_FILE, ['http_errors' => false]);

        if ($response->getStatusCode() < Response::HTTP_OK || $response->getStatusCode() >= Response::HTTP_MULTIPLE_CHOICES) {
            return false;
        }

        if ($this->filesystem->has(self::LOCAL_FILE)) {
            $this->filesystem->delete(self::LOCAL_FILE);
        }

        $this->filesystem->write(self::LOCAL_FILE, (string) $response->getBody(), ['ContentType' => 'text/plain']);

        return true;
    }
}
