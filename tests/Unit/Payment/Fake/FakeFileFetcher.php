<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaException;
use Symfony\Component\HttpFoundation\Request;

final class FakeFileFetcher extends FileFetcher
{
    /** @var list<string> */
    public array $requestedUrls = [];

    public function __construct(private readonly bool $iconAvailable = true)
    {
    }

    public function fetchFileFromURL(Request $request, string $fileName): MediaFile
    {
        $this->requestedUrls[] = (string) $request->request->get('url');

        if (! $this->iconAvailable) {
            throw MediaException::cannotOpenSourceStreamToRead($fileName);
        }

        // PaymentMethodInstaller::cleanupTempFile() unlinks this path when it exists, so it
        // must never collide with a real file on the machine running the suite.
        return new MediaFile(
            sys_get_temp_dir() . '/mollie-fake-icon-' . bin2hex(random_bytes(8)),
            'image/svg+xml',
            'svg',
            1024
        );
    }
}
