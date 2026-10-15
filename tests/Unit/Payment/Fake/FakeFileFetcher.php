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

    /** @var list<string> */
    public array $destinationPaths = [];

    public function __construct(private readonly bool $iconAvailable = true)
    {
    }

    public function fetchFileFromURL(Request $request, string $fileName): MediaFile
    {
        $this->requestedUrls[] = (string) $request->request->get('url');
        $this->destinationPaths[] = $fileName;

        if (! $this->iconAvailable) {
            throw MediaException::cannotOpenSourceStreamToRead($fileName);
        }

        return new MediaFile(
            $fileName,
            'image/svg+xml',
            'svg',
            1024
        );
    }
}
