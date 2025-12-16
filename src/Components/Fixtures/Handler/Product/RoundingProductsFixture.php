<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Product;

use Kiener\MolliePayments\Components\Fixtures\FixtureUtils;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\Traits\ProductFixtureTrait;
use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class RoundingProductsFixture implements MollieFixtureHandlerInterface
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
        $category = 'Rounding';
        $image = 'tshirt-white.png';
        $description = 'Product to test rounding issues.';

        $this->createProduct('7d1abedd2d22436385580e2ff42431b9', 'Product A 4 Decimals', 'MOL_ROUNDING_1', $category, $description, 2.7336, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('6d1abedd2d22436485580f3ff42431b9', 'Product B 4 Decimals', 'MOL_ROUNDING_2', $category, $description, 2.9334, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('1a2abeed2d22436485580f3ff42431b9', 'Product C 4 Decimals', 'MOL_ROUNDING_3', $category, $description, 1.6494, $image, false, [], $this->repoProducts, $this->utils);
    }

    public function uninstall(): void
    {
        $this->repoProducts->delete(
            [
                ['id' => '7d1abedd2d22436385580e2ff42431b9'],
                ['id' => '6d1abedd2d22436485580f3ff42431b9'],
                ['id' => '1a2abeed2d22436485580f3ff42431b9']
            ],
            Context::createDefaultContext()
        );
    }
}
