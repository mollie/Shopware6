<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

use League\Flysystem\FilesystemInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class ApplePayDomainVerificationService
{

    /**
     * This is the static download URL for all Mollie merchants.
     * This one must not change! But the content might change, you'll never know.
     */
    public const URL_FILE = 'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association';

    /**
     * This is the local path where the file needs to be stored and will
     * be accessed by Apple. It is a relative path from the DocRoot.
     */
    public const LOCAL_FILE = '/.well-known/apple-developer-merchantid-domain-association';


    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var RequestFactoryInterface
     */
    private $httpRequestFactory;

    /**
     * @param FilesystemInterface $filesystem
     */
    public function __construct(
        FilesystemInterface     $filesystem,
        ClientInterface         $httpClient,
        RequestFactoryInterface $httpRequestFactory
    ) {
        $this->filesystem = $filesystem;
        $this->httpClient = $httpClient;
        $this->httpRequestFactory = $httpRequestFactory;
    }

    /**
     * Downloads the Mollie Domain-Verification file to the
     * local shop directories.
     */
    public function downloadDomainAssociationFile(): void
    {
        try {
            $response = $this->httpClient->sendRequest($this->httpRequestFactory->createRequest('GET', self::URL_FILE));
        } catch (\Throwable $_) {
            return;
        }

        // the client should support follow redirect out of the box
        if ($response->getStatusCode() >= 300) {
            return;
        }

        // should never happen as PSR describes that 1XX should be managed by the HttpClient
        if ($response->getStatusCode() < 200) {
            return;
        }

        $body = (string) $response->getBody();
        $this->filesystem->put(self::LOCAL_FILE, (string) $response->getBody());
    }
}
