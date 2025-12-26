<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Product\Traits;

use Kiener\MolliePayments\Components\Fixtures\FixtureUtils;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxEntity;

trait ProductFixtureTrait
{
    private FixtureUtils $utils;

    /**
     * @param array<mixed> $customFields
     * @param EntityRepository<ProductCollection> $repoProducts
     */
    protected function createProduct(string $id, string $name, string $number, string $categoryName, string $description, float $price, string $image, bool $shippingFree, array $customFields, $repoProducts, FixtureUtils $fixtureUtils,float $taxRateValue = 19.0): void
    {
        $this->utils = $fixtureUtils;

        // just reuse the product one ;)
        $mediaId = $id;
        $visibilityID = $id;
        $coverId = $id;

        // we have to avoid duplicate images (shopware has a problem with it in media)
        // so lets copy it for our id
        $imageSource = __DIR__ . '/../Assets/' . $image;
        $imagePath = __DIR__ . '/../Assets/' . $id . '_' . $image;
        copy($imageSource, $imagePath);

        $defaultFolder = $this->utils->getMedia()->getDefaultFolder('product');

        if (! $defaultFolder instanceof MediaFolderEntity) {
            throw new \RuntimeException('Could not find default media folder for products.');
        }

        $this->utils->getMedia()->upload(
            $mediaId,
            $defaultFolder->getId(),
            $imagePath,
            'png',
            'image/png',
        );

        // delete our temp file again
        unlink($imagePath);

        $salesChannel = $this->utils->getSalesChannels()->getStorefrontSalesChannel();

        if (! $salesChannel instanceof SalesChannelEntity) {
            throw new \RuntimeException('Could not find storefront sales channel.');
        }

        $taxRate = $this->utils->getTaxes()->getTax($taxRateValue);

        if (! $taxRate instanceof TaxEntity) {
            throw new \RuntimeException('Could not find ' . $taxRateValue . '% tax rate for products.');
        }

        $repoProducts->upsert(
            [
                [
                    'id' => $id,
                    'name' => $name,
                    'taxId' => $taxRate->getId(),
                    'productNumber' => $number,
                    'description' => $description,
                    'visibilities' => [
                        [
                            'id' => $visibilityID,
                            'salesChannelId' => $salesChannel->getId(),
                            'visibility' => 30,
                        ]
                    ],
                    'categories' => [
                        [
                            'id' => Uuid::fromStringToHex($categoryName),
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
                ]
            ],
            Context::createDefaultContext()
        );
    }
}
