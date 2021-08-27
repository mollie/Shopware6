<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\ApplePayDirect;

use League\Flysystem\FilesystemInterface;

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
     * @param FilesystemInterface $filesystem
     */
    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Downloads the Mollie Domain-Verification file to the
     * local shop directories.
     */
    public function downloadDomainAssociationFile(): void
    {
        $content = file_get_contents(self::URL_FILE);

        $this->filesystem->put(self::LOCAL_FILE, $content);
    }

}
