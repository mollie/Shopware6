<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Product;

use Kiener\MolliePayments\Components\Fixtures\FixtureUtils;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\Traits\ProductFixtureTrait;
use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class SubscriptionProductsFixture implements MollieFixtureHandlerInterface
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
        $category = 'Subscriptions';
        $image = 'champagne.png';
        $description = 'Mollie Subscription Product for testing purpose in development environment.';

        $customFieldsDaily = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => 'days'
        ];

        $customFieldsWeekly = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => 'weeks'
        ];

        $customFieldsThreeWeekly = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 3,
            'mollie_payments_product_subscription_interval_unit' => 'weeks'
        ];

        $this->createProduct('1d1eeedd6d22436385580e2ff42431b9', 'Subscription (1x Daily)', 'MOL_SUB_1', $category, $description, 19, $image, false, $customFieldsDaily, $this->repoProducts, $this->utils);
        $this->createProduct('1d2eeedd6d22436385580e2ff42431b9', 'Subscription (1x Weekly)', 'MOL_SUB_2', $category, $description, 29, $image, false, $customFieldsWeekly, $this->repoProducts, $this->utils);
        $this->createProduct('1d2eeedd6d22436385580e2ff42431c6', 'Subscription (3x Weekly)', 'MOL_SUB_3', $category, $description, 29, $image, false, $customFieldsThreeWeekly, $this->repoProducts, $this->utils);
    }

    public function uninstall(): void
    {
        $this->repoProducts->delete(
            [
                ['id' => '1d1eeedd6d22436385580e2ff42431b9'],
                ['id' => '1d2eeedd6d22436385580e2ff42431b9'],
                ['id' => '1d2eeedd6d22436385580e2ff42431c6']
            ],
            Context::createDefaultContext()
        );
    }
}
