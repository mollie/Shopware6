<?php

namespace MolliePayments\Fixtures\Product;

use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use MolliePayments\Fixtures\Category\CategoryFixture;
use MolliePayments\Fixtures\Product\Traits\ProductFixtureTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class FailureProducts extends Fixture
{
    use ProductFixtureTrait;


    /**
     * @var EntityRepository
     */
    private $repoProducts;


    /**
     * @param FixtureHelper $helper
     * @param EntityRepository $repoProducts
     */
    public function __construct(FixtureHelper $helper, EntityRepository $repoProducts)
    {
        $this->helper = $helper;
        $this->repoProducts = $repoProducts;
    }

    /**
     * @return string[]
     */
    public function groups(): array
    {
        return [
            'mollie',
            'mollie-demodata',
        ];
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
    public function load(): void
    {
        $category = 'Failures';
        $image = 'tshirt-black-fail.png';
        $description = 'Mollie Product for testing purpose in development environment. Use "failed" on the Mollie Payment Sandbox page to force the special error reason of this product.';


        $this->createProduct('0d1eeedd6d22436385580e2ff42431b9', 'Invalid Card Number', 'MOL_ERROR_1', $category, $description, 1001, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d2eeedd6d22436385580e2ff42431b9', 'Invalid CVV', 'MOL_ERROR_2', $category, $description, 1002, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d3eeedd6d22436385580e2ff42431b9', 'Invalid Card Holder Name', 'MOL_ERROR_3', $category, $description, 1003, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d4eeedd6d22436385580e2ff42431b9', 'Card Expired', 'MOL_ERROR_4', $category, $description, 1004, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d5eeedd6d22436385580e2ff42431b9', 'Invalid Card Type', 'MOL_ERROR_5', $category, $description, 1005, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d6eeedd6d22436385580e2ff42431b9', 'Refused by Issuer', 'MOL_ERROR_6', $category, $description, 1006, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d7eeedd6d22436385580e2ff42431b9', 'Insufficient Funds', 'MOL_ERROR_7', $category, $description, 1007, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d8eeedd6d22436385580e2ff42431b9', 'Inactive Card', 'MOL_ERROR_8', $category, $description, 1008, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d9eeedd6d22436385580e2ff42431b9', 'Possible Fraud', 'MOL_ERROR_9', $category, $description, 1009, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d3eeedd6d10436385580e2ff42431b9', 'Authentication Failed', 'MOL_ERROR_10', $category, $description, 1010, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('0d3eeedd6d11436385580e2ff42431b9', 'Card Declined', 'MOL_ERROR_11', $category, $description, 1011, $image, false, [], $this->repoProducts, $this->helper);
    }
}
