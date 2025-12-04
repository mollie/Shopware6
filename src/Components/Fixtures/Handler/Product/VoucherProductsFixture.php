<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Product;

use Kiener\MolliePayments\Components\Fixtures\FixtureUtils;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\Traits\ProductFixtureTrait;
use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class VoucherProductsFixture implements MollieFixtureHandlerInterface
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

    public function install(): void
    {
        $category = 'Voucher';
        $image = 'tshirt-white.png';
        $description = 'Mollie Voucher Product for testing purpose in development environment.';

        $customFieldsEco = [
            'mollie_payments_product_voucher_type' => '1',
        ];

        $customFieldsMeal = [
            'mollie_payments_product_voucher_type' => '2',
        ];

        $customFieldsGift = [
            'mollie_payments_product_voucher_type' => '3',
        ];

        $this->createProduct('4d1eeedd6d22436385580e2ff42431b9', 'Voucher ECO', 'MOL_VOUCHER_1', $category, $description, 19, $image, false, $customFieldsEco, $this->repoProducts, $this->utils);
        $this->createProduct('5d1eeedd6d22436385580e2ff42431b9', 'Voucher MEAL', 'MOL_VOUCHER_2', $category, $description, 19, 'champagne.png', false, $customFieldsMeal, $this->repoProducts, $this->utils);
        $this->createProduct('6d1eeedd6d22436385580e2ff42431b9', 'Voucher GIFT', 'MOL_VOUCHER_3', $category, $description, 19, $image, false, $customFieldsGift, $this->repoProducts, $this->utils);
    }
}
