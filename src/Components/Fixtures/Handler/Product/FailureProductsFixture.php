<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Product;

use Kiener\MolliePayments\Components\Fixtures\FixtureUtils;
use Kiener\MolliePayments\Components\Fixtures\Handler\Category\CategoryFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\Traits\ProductFixtureTrait;
use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class FailureProductsFixture implements MollieFixtureHandlerInterface
{
    use ProductFixtureTrait;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repoProducts;

    /**
     * @param EntityRepository<ProductCollection> $repoProducts
     */
    public function __construct(FixtureUtils $utils, EntityRepository $repoProducts)
    {
        $this->utils = $utils;
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

    public function install(): void
    {
        $category = 'Failures';
        $image = 'tshirt-black-fail.png';
        $description = 'Mollie Product for testing purpose in development environment. Use "failed" on the Mollie Payment Sandbox page to force the special error reason of this product.';

        $this->createProduct('0d1eeedd6d22436385580e2ff42431b9', 'Invalid Card Number', 'MOL_ERROR_1', $category, $description, 1001, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d2eeedd6d22436385580e2ff42431b9', 'Invalid CVV', 'MOL_ERROR_2', $category, $description, 1002, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d3eeedd6d22436385580e2ff42431b9', 'Invalid Card Holder Name', 'MOL_ERROR_3', $category, $description, 1003, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d4eeedd6d22436385580e2ff42431b9', 'Card Expired', 'MOL_ERROR_4', $category, $description, 1004, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d5eeedd6d22436385580e2ff42431b9', 'Invalid Card Type', 'MOL_ERROR_5', $category, $description, 1005, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d6eeedd6d22436385580e2ff42431b9', 'Refused by Issuer', 'MOL_ERROR_6', $category, $description, 1006, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d7eeedd6d22436385580e2ff42431b9', 'Insufficient Funds', 'MOL_ERROR_7', $category, $description, 1007, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d8eeedd6d22436385580e2ff42431b9', 'Inactive Card', 'MOL_ERROR_8', $category, $description, 1008, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d9eeedd6d22436385580e2ff42431b9', 'Possible Fraud', 'MOL_ERROR_9', $category, $description, 1009, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d3eeedd6d10436385580e2ff42431b9', 'Authentication Failed', 'MOL_ERROR_10', $category, $description, 1010, $image, false, [], $this->repoProducts, $this->utils);
        $this->createProduct('0d3eeedd6d11436385580e2ff42431b9', 'Card Declined', 'MOL_ERROR_11', $category, $description, 1011, $image, false, [], $this->repoProducts, $this->utils);
    }
}
