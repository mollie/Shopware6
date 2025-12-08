<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Utils;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class MediaUtils
{
    /**
     * @var EntityRepository<MediaCollection>
     */
    private $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private $mediaFolderRepository;

    private FileSaver $fileSaver;
    private FileFetcher $fileFetcher;

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     */
    public function __construct(
        $mediaRepository,
        $mediaFolderRepository,
        FileSaver $fileSaver,
        FileFetcher $fileFetcher
    ) {
        $this->fileFetcher = $fileFetcher;
        $this->fileSaver = $fileSaver;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * Copied from "vendor/shopware/core/Content/Media/MediaService.php".
     *
     * Extended it to include simple cache
     */
    public function getDefaultFolder(string $folderName): ?MediaFolderEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $folderName))
            ->addAssociation('defaultFolder')
            ->setLimit(1)
        ;

        $criteria->setTitle(\sprintf('%s::%s()', __CLASS__, __FUNCTION__));

        $mediaFolder = $this->mediaFolderRepository
            ->search($criteria, Context::createDefaultContext())
            ->first()
        ;

        return $mediaFolder instanceof MediaFolderEntity ? $mediaFolder : null;
    }

    /**
     * "Upload" a file within shopware. It takes a real file path ($filename) and uploads it as a full media.
     */
    public function upload(string $mediaId, string $folderId, string $filename, string $extension, string $contentType): void
    {
        $ctx = Context::createDefaultContext();

        $this->mediaRepository->upsert(
            [
                [
                    'id' => $mediaId,
                    'mediaFolderId' => $folderId,
                ],
            ],
            $ctx,
        );

        $uploadedFile = $this->fileFetcher->fetchBlob(
            (string) file_get_contents($filename),
            $extension,
            $contentType,
        );

        $this->fileSaver->persistFileToMedia(
            $uploadedFile,
            basename($filename, '.' . $extension),
            $mediaId,
            $ctx,
        );
    }
}
