<?php

namespace MolliePayments\Fixtures\Product\Traits;


use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

trait ProductFixtureTrait
{

    /**
     * @param string $id
     * @param string $name
     * @param string $number
     * @param string $categoryName
     * @param float $price
     * @param string $image
     * @param array $customFields
     * @param EntityRepositoryInterface $repoProducts
     * @param FixtureHelper $helper
     */
    protected function createProduct(string $id, string $name, string $number, string $categoryName, float $price, string $image, array $customFields, EntityRepositoryInterface $repoProducts, FixtureHelper $helper): void
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

        $repoProducts->upsert([
            [
                'id' => $id,
                'name' => $name,
                'taxId' => $helper->SalesChannel()->getTax19()->getId(),
                'productNumber' => $number,
                'description' => 'Mollie Product for testing purpose in development environment. Use "failed" on the Mollie Payment Sandbox page to force the special error reason of this product.',
                'visibilities' => [
                    [
                        'id' => $visibilityID,
                        'salesChannelId' => $helper->SalesChannel()->getStorefrontSalesChannel()->getId(),
                        'visibility' => 30,
                    ]
                ],
                'categories' => [
                    [
                        'id' => $helper->Category()->getByName($categoryName)->getId(),
                    ]
                ],
                'stock' => 10,
                'price' => [
                    [
                        'currencyId' => $helper->SalesChannel()->getCurrencyEuro()->getId(),
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
            ]
        ],
            Context::createDefaultContext()
        );
    }

}