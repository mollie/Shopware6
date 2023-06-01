<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use League\Flysystem\Filesystem;

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
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var HttpClientInterface
     */
    private $httpClient;


    /**
     * @param Filesystem $filesystem
     * @param HttpClientInterface $httpClient
     */
    public function __construct(Filesystem $filesystem, HttpClientInterface $httpClient)
    {
        $this->filesystem = $filesystem;
        $this->httpClient = $httpClient;
    }

    /**
     * Downloads the Mollie Domain-Verification file to the
     * local shop directories.
     */
    public function downloadDomainAssociationFile(): void
    {
        $response = $this->httpClient->sendRequest('GET', self::URL_FILE);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        if ($this->filesystem->has(self::LOCAL_FILE)) {
            $this->filesystem->delete(self::LOCAL_FILE);
        }

        $this->filesystem->write(self::LOCAL_FILE, $response->getBody());
    }
}
