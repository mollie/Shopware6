<?php

namespace MolliePayments\Fixtures\Product;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class SubscriptionFixture extends Fixture
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
    }
}