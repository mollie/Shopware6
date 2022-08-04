<?php

namespace MolliePayments\Fixtures\Product;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use MolliePayments\Fixtures\Category\CategoryFixture;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class TestingFailureProducts extends Fixture
{

    private const MEDIA_ID = '0d2ccefd6d22436385580e2ff42431b9';
    private const PRODUCT_ID = '0d8ffedd6d22436385580e2ff42431b9';
    private const COVER_ID = '0d8fffdd6d33436353580e2ad42421b9';

    /**
     * @var FixtureHelper
     */
    private $helper;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoProducts;


    /**
     * @param FixtureHelper $helper
     * @param EntityRepositoryInterface $repoProducts
     */
    public function __construct(FixtureHelper $helper, EntityRepositoryInterface $repoProducts)
    {
        $this->helper = $helper;
        $this->repoProducts = $repoProducts;
    }

    /**
     * @return string[]
     */
    public function dependsOn(): array
    {
        return [
            CategoryFixture::class
        ];
    }


    /**
     * @param FixtureBag $bag
     * @return void
     */
    public function load(FixtureBag $bag): void
    {
        $this->helper->Media()->upload(
            self::MEDIA_ID,
            $this->helper->Media()->getDefaultFolder('product')->getId(),
            __DIR__ . '/Assets/subscription-product.png',
            'png',
            'image/png',
        );


        $this->repoProducts->upsert([
            [
                'id' => self::PRODUCT_ID,
                'name' => 'Subscription Product',
                'taxId' => $this->helper->SalesChannel()->getTax19()->getId(),
                'productNumber' => 'MOL1',
                'description' => 'Mollie Subscription Product for testing purpose in development environment.',
                'visibilities' => [
                    [
                        'id' => '0d8fffdd6d33436353580e2ad42431b9',
                        'salesChannelId' => $this->helper->SalesChannel()->getStorefrontSalesChannel()->getId(),
                        'visibility' => 30,
                    ]
                ],
                'categories' => [
                    [
                        'id' => $this->helper->Category()->getByName('Clothing')->getId(),
                    ]
                ],
                'stock' => 10,
                'price' => [
                    [
                        'currencyId' => $this->helper->SalesChannel()->getCurrencyEuro()->getId(),
                        'gross' => 19.99,
                        'net' => 16.80,
                        'linked' => true,
                    ]
                ],
                'customFields' => [
                    'mollie_payments_product_subscription_enabled' => true,
                    'mollie_payments_product_subscription_interval' => 1,
                    'mollie_payments_product_subscription_interval_unit' => "days"
                ],
                'media' => [
                    [
                        'id' => self::COVER_ID,
                        'mediaId' => self::MEDIA_ID,
                    ]
                ],
                'coverId' => self::COVER_ID,
            ]
        ],
            Context::createDefaultContext()
        );

        $category = 'Testing Failures';

        $this->createProduct('0d1eeedd6d22436385580e2ff42431b9', 'Invalid Card Number', 'MOL_ERROR_1', $category, 1001, 'subscription-product.png');
        $this->createProduct('0d2eeedd6d22436385580e2ff42431b9', 'Invalid CVV', 'MOL_ERROR_2', $category, 1002, 'subscription-product.png');
        $this->createProduct('0d3eeedd6d22436385580e2ff42431b9', 'Invalid Card Holder Name', 'MOL_ERROR_3', $category, 1003, 'subscription-product.png');
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $number
     * @param string $categoryName
     * @param float $price
     * @param string $image
     * @return void
     */
    private function createProduct(string $id, string $name, string $number, string $categoryName, float $price, string $image): void
    {
        # just reuse the product one ;)
        $mediaId = $id;
        $visibilityID = $id;
        $coverId = $id;

        # we have to avoid duplicate images (shopware has a problem with it in media)
        # so lets copy it for our id
        $imageSource = __DIR__ . '/Assets/' . $image;
        $imagePath = __DIR__ . '/Assets/' . $id . '_' . $image;
        copy($imageSource, $imagePath);


        $this->helper->Media()->upload(
            $mediaId,
            $this->helper->Media()->getDefaultFolder('product')->getId(),
            $imagePath,
            'png',
            'image/png',
        );

        # delete our temp file again
        unlink($imagePath);

        $this->repoProducts->upsert([
            [
                'id' => $id,
                'name' => $name,
                'taxId' => $this->helper->SalesChannel()->getTax19()->getId(),
                'productNumber' => $number,
                'description' => 'Mollie Product for testing purpose in development environment. Use "failed" on the Mollie Payment Sandbox page to force the special error reason of this product.',
                'visibilities' => [
                    [
                        'id' => $visibilityID,
                        'salesChannelId' => $this->helper->SalesChannel()->getStorefrontSalesChannel()->getId(),
                        'visibility' => 30,
                    ]
                ],
                'categories' => [
                    [
                        'id' => $this->helper->Category()->getByName($categoryName)->getId(),
                    ]
                ],
                'stock' => 10,
                'price' => [
                    [
                        'currencyId' => $this->helper->SalesChannel()->getCurrencyEuro()->getId(),
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
            ]
        ],
            Context::createDefaultContext()
        );
    }
}