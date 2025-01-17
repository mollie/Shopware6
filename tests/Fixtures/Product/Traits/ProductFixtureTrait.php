<?php

namespace MolliePayments\Fixtures\Product\Traits;

use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductFixtureTrait
{
    /**
     * @param string $id
     * @param string $name
     * @param string $number
     * @param string $categoryName
     * @param string $description
     * @param float $price
     * @param string $image
     * @param bool $shippingFree
     * @param array $customFields
     * @param EntityRepository $repoProducts
     * @param FixtureHelper $helper
     * @return void
     */
    protected function createProduct(string $id, string $name, string $number, string $categoryName, string $description, float $price, string $image, bool $shippingFree, array $customFields, EntityRepository $repoProducts, FixtureHelper $helper): void
    {
        # just reuse the product one ;)
        $mediaId = $id;
        $visibilityID = $id;
        $coverId = $id;

        # we have to avoid duplicate images (shopware has a problem with it in media)
        # so lets copy it for our id
        $imageSource = __DIR__ . '/../Assets/' . $image;
        $imagePath = __DIR__ . '/../Assets/' . $id . '_' . $image;
        copy($imageSource, $imagePath);


        $helper->Media()->upload(
            $mediaId,
            $helper->Media()->getDefaultFolder('product')->getId(),
            $imagePath,
            'png',
            'image/png',
        );

        # delete our temp file again
        unlink($imagePath);

        $repoProducts->upsert(
            [
                [
                    'id' => $id,
                    'name' => $name,
                    'taxId' => $helper->Tax()->getTax19()->getId(),
                    'productNumber' => $number,
                    'description' => $description,
                    'visibilities' => [
                        [
                            'id' => $visibilityID,
                            'salesChannelId' => $helper->SalesChannel()->getStorefrontSalesChannel()->getId(),
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
