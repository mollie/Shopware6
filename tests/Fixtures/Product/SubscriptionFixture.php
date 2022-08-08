<?php

namespace MolliePayments\Fixtures\Product;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use MolliePayments\Fixtures\Product\Traits\ProductFixtureTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class SubscriptionFixture extends Fixture
{

    use ProductFixtureTrait;

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
        $category = 'Subscriptions';
        $image = 'champagne.png';

        $customFieldsDaily = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => "days"
        ];

        $customFieldsWeekly = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => "week"
        ];

        $this->createProduct('1d1eeedd6d22436385580e2ff42431b9', 'Subscription (1x Daily)', 'MOL_SUB_1', $category, 19, $image, $customFieldsDaily, $this->repoProducts, $this->helper);
        $this->createProduct('1d2eeedd6d22436385580e2ff42431b9', 'Subscription (1x Weekly)', 'MOL_SUB_2', $category, 29, $image, $customFieldsWeekly, $this->repoProducts, $this->helper);
    }

}