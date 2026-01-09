<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Product;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductTrait
{
    private function getProductId(string $productNumber): string
    {
        return Uuid::fromStringToHex($productNumber);
    }

    /**
     * @param array<mixed> $customFields
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private function getProductData(string $name, string $productNumber, string $description, string $mediaId, string $category, string $salesChannelId, float $price, float $taxRate = 19.0, int $stock = 10, bool $shippingFree = false, ?string $parentId = null, array $customFields = [], array $options = []): array
    {
        $productId = $this->getProductId($productNumber);
        $parts = explode('.', (string) $price);
        $decimals = isset($parts[1]) ? strlen($parts[1]) : 0;

        $netPrice = round($price / (1 + $taxRate / 100), $decimals);
        $productMediaId = Uuid::fromStringToHex(sprintf('%s-%s', $productId, $mediaId));
        $variantListingConfig = [];
        $configuratorSettings = [];
        if ($parentId === null && count($options) > 0) {
            $variantListingConfig = [
                'displayParent' => true
            ];
            foreach ($options as $option) {
                $configuratorSettings[] = [
                    'id' => Uuid::fromStringToHex(sprintf('%s-%s', $productId, $option['id'])),
                    'option' => $option,
                ];
            }
        }

        return [
            'id' => $productId,
            'name' => $name,
            'parentId' => $parentId,
            'taxId' => Uuid::fromStringToHex('tax-' . $taxRate),
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
            'stock' => $stock,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $price,
                    'net' => $netPrice,
                    'linked' => true,
                ]
            ],
            'media' => [
                [
                    'id' => $productMediaId,
                    'mediaId' => $mediaId,
                ]
            ],
            'coverId' => $productMediaId,
            'customFields' => $customFields,
            'shippingFree' => $shippingFree,
            'options' => $options,
            'properties' => $options,
            'variantListingConfig' => $variantListingConfig,
            'configuratorSettings' => $configuratorSettings,
            'manufacturer' => [
                'id' => Uuid::fromStringToHex('mollie-manufacturer'),
                'name' => 'Mollie',
            ]
        ];
    }

    /**
     * @param array<string> $images
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @return array<string,string>
     */
    private function getMediaMapping(array $images, Context $context): array
    {
        $criteria = new Criteria();
        $criteriaImages = array_map(function ($image) {
            $extension = pathinfo($image, PATHINFO_EXTENSION);

            return str_replace('.' . $extension, '', $image);
        }, $images);

        $criteria->addFilter(new EqualsAnyFilter('fileName', $criteriaImages));
        $mediaRepository = $this->container->get('media.repository');
        $searchResult = $mediaRepository->search($criteria, $context);
        $requestedImages = array_flip($images);

        $result = [];
        /** @var MediaEntity $media */
        foreach ($searchResult as $media) {
            $key = sprintf('%s.%s', $media->getFileName(), $media->getFileExtension());

            $result[$key] = $media->getId();
            unset($requestedImages[$key]);
        }

        foreach ($requestedImages as $image => $number) {
            $filePath = __DIR__ . '/Assets/' . $image;
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                continue;
            }

            $fileInfo = new \finfo();
            $contentType = (string) $fileInfo->file($filePath, FILEINFO_MIME_TYPE);
            $extension = (string) $fileInfo->file($filePath, FILEINFO_EXTENSION);
            $fileName = str_replace('.' . $extension, '', $image);

            $mediaFile = $this->fileFetcher->fetchBlob($fileContent, $extension, $contentType);
            $mediaId = $this->mediaService->saveMediaFile($mediaFile, $fileName, $context, 'product', null, false);
            $result[$image] = $mediaId;
            unset($requestedImages[$image]);
        }

        return $result;
    }
}
