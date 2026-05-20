<?php

namespace MolliePayments\Shopware\Fixtures\Product;

use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use MolliePayments\Shopware\Fixtures\Product\Traits\ProductFixtureTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class VoucherFixture extends Fixture
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
        $category = 'Voucher';
        $image = 'tshirt-white.png';
        $description = 'Mollie Voucher Product for testing purpose in development environment.';

        $customFieldsEco = [
            'mollie_payments_product_voucher_type' => 1,
        ];

        $customFieldsMeal = [
            'mollie_payments_product_voucher_type' => 2,
        ];

        $customFieldsGift = [
            'mollie_payments_product_voucher_type' => 3,
        ];

        $this->createProduct('4d1eeedd6d22436385580e2ff42431b9', 'Voucher ECO', 'MOL_VOUCHER_1', $category, $description, 19, $image, false, $customFieldsEco, $this->repoProducts, $this->helper);
        $this->createProduct('5d1eeedd6d22436385580e2ff42431b9', 'Voucher MEAL', 'MOL_VOUCHER_2', $category, $description, 19, 'champagne.png', false, $customFieldsMeal, $this->repoProducts, $this->helper);
        $this->createProduct('6d1eeedd6d22436385580e2ff42431b9', 'Voucher GIFT', 'MOL_VOUCHER_3', $category, $description, 19, $image, false, $customFieldsGift, $this->repoProducts, $this->helper);
    }
}
