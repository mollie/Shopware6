<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use League\Flysystem\FilesystemInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class ApplePayDomainVerificationService
{
    public const URL_FILE = 'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association';

    private const LOCAL_FILE = '/.well-known/apple-developer-merchantid-domain-association';

    private $filesystem;

    public function __construct(FilesystemInterface $filesystem) {
        $this->filesystem = $filesystem;
    }

    public function downloadDomainAssociationFile() : void {
        $content = file_get_contents(self::URL_FILE);

        $this->filesystem->put(self::LOCAL_FILE, $content);
    }
}
