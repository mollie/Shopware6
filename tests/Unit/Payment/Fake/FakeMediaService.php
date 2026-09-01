<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;

final class FakeMediaService extends MediaService
{
    /** @var list<string> */
    public array $savedFileNames = [];

    public function __construct(private readonly string $mediaId = 'media-1')
    {
    }

    public function saveMediaFile(MediaFile $mediaFile, string $filename, Context $context, ?string $folder = null, ?string $mediaId = null, bool $private = true): string
    {
        $this->savedFileNames[] = $filename;

        return $this->mediaId;
    }
}
