<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Product;

use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductTrait
{
    private function getProductId(string $productNumber): string
    {
        return Uuid::fromStringToHex($productNumber);
    }

    private function getProductData(string $name, string $productNumber, string $description, string $image, string $category, string $salesChannelId, float $price, ?float $taxRate = null, bool $shippingFree = false, array $customFields = []): array
    {
        $productId = $this->getProductId($productNumber);
        return [
            'id' => $productId,
            'name' => $name,
            'taxId' => $taxRate->getId(),
            'productNumber' => $productNumber,
            'description' => $description,
            'visibilities' => [
                [
                    'id' => Uuid::fromStringToHex(sprintf('%s-%s', $productId, $salesChannelId)),
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ]
            ],
            'categories' => [
                [
                    'id' => Uuid::fromStringToHex('mollie-' . $category),
                ]
            ],
            'stock' => 10,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $price,
                    'net' => $price,
                    'linked' => true,
                ]
            ],
            'media' => [
                [
                    'id' => $coverId,
                    'mediaId' => $mediaId,
                ]
            ],
            'coverId' => $coverId,
            'customFields' => $customFields,
            'shippingFree' => $shippingFree,
        ];
    }

    private function getMediaId(string $image): string
    {


        $filePath = __DIR__ . '/Assets/' . $image;
        $fileInfo = new \finfo();
        $contentType = $fileInfo->file($filePath, FILEINFO_MIME_TYPE);
        $extension = $fileInfo->file($filePath, FILEINFO_EXTENSION);
        $fileContent = file_get_contents($filePath);
        $mediaFile = $this->fileFetcher->fetchBlob($fileContent,$extension,$contentType);
        $this->fileFetcher->cleanUpTempFile($mediaFile);
        dump($mediaFile);
        return '';
        // $mediaFile = $fileFetcher->fetchBlob(file_get_contents($filePath),$splFileInfo->getExtension(),$splFileInfo->getType());
    }

    private function getMediaMapping(array $images,Context $context):array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('fileName', $images));
        $mediaRepository = $this->container->get('media.repository');
        $searchResult = $mediaRepository->search($criteria, $context);

        $result = [];
        /** @var MediaEntity $media */
        foreach ($searchResult as $media) {
            $key = sprintf('%s.%s',$media->getFileName(),$media->getFileExtension());
            $result[$key] = $media->getId();
        }

        return $result;
    }
}