<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Product;

use Kiener\MolliePayments\Components\Fixtures\FixtureUtils;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\Traits\ProductFixtureTrait;
use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class CheapProductsFixture implements MollieFixtureHandlerInterface
{
    use ProductFixtureTrait;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private $repoProducts;

    /**
     * @param EntityRepository<ProductCollection> $repoProducts
     */
    public function __construct(FixtureUtils $utils, $repoProducts)
    {
        $this->utils = $utils;
        $this->repoProducts = $repoProducts;
    }

    public function install(): void
    {
        $category = 'Cheap';
        $image = 'tshirt-black.png';
        $description = 'Mollie Product for testing purpose in development environment. You can use this cheap products for LIVE tests or other scenarios';

        $this->createProduct('1d3eefdd2d22436385580e2fb43431b9', 'Cheap Mollie Shirt', 'MOL_CHEAP_1', $category, $description, 1, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('1d3eefdd2d22436385580e2fb53432b2', 'Regular Mollie Shirt', 'MOL_CHEAP_2', $category, $description, 29.90, $image, false, [], $this->repoProducts, $this->utils);
    }

    public function uninstall(): void
    {
        $this->repoProducts->delete(
            [
                ['id' => '1d3eefdd2d22436385580e2fb43431b9'],
                ['id' => '1d3eefdd2d22436385580e2fb53432b2'],
            ],
            Context::createDefaultContext()
        );
    }
}
