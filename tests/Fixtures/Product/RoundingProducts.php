<?php

namespace MolliePayments\Shopware\Fixtures\Product;

use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use MolliePayments\Shopware\Fixtures\Product\Traits\ProductFixtureTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class RoundingProducts extends Fixture
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
     * @param FixtureBag $bag
     * @return void
     */
    public function load(): void
    {
        $category = 'Rounding';
        $image = 'tshirt-white.png';
        $description = 'Product to test rounding issues.';

        $this->createProduct('7d1abedd2d22436385580e2ff42431b9', 'Product A 4 Decimals', 'MOL_ROUNDING_1', $category, $description, 2.7336, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('6d1abedd2d22436485580f3ff42431b9', 'Product B 4 Decimals', 'MOL_ROUNDING_2', $category, $description, 2.9334, $image, false, [], $this->repoProducts, $this->helper);
        $this->createProduct('1a2abeed2d22436485580f3ff42431b9', 'Product C 4 Decimals', 'MOL_ROUNDING_3', $category, $description, 1.6494, $image, false, [], $this->repoProducts, $this->helper);
    }
}
