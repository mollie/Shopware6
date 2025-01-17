<?php

namespace MolliePayments\Fixtures\Product;

use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use MolliePayments\Fixtures\Category\CategoryFixture;
use MolliePayments\Fixtures\Product\Traits\ProductFixtureTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class CheapProducts extends Fixture
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
        $category = 'Cheap';
        $image = 'tshirt-black.png';
        $description = 'Mollie Product for testing purpose in development environment. You can use this cheap products for LIVE tests or other scenarios';

        $this->createProduct('1d3eefdd2d22436385580e2fb43431b9', 'Cheap Mollie Shirt', 'MOL_CHEAP_1', $category, $description, 1, $image, true, [], $this->repoProducts, $this->helper);
    }
}
