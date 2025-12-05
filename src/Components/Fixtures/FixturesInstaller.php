<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures;

use Kiener\MolliePayments\Components\Fixtures\Handler\Category\CategoryFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\CheapProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\FailureProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\RoundingProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\SubscriptionProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\VoucherProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\SalesChannel\SalesChannelFixture;

class FixturesInstaller
{
    private CategoryFixture $categoryFixture;
    private SalesChannelFixture $salesChannelFixture;
    private SubscriptionProductsFixture $subscriptionFixture;
    private VoucherProductsFixture $voucherFixture;
    private CheapProductsFixture $cheapProducts;
    private FailureProductsFixture $failureProducts;
    private RoundingProductsFixture $roundingProducts;

    public function __construct(
        CategoryFixture $categoryFixture,
        SalesChannelFixture $salesChannelFixture,
        SubscriptionProductsFixture $subscriptionFixture,
        VoucherProductsFixture $voucherFixture,
        CheapProductsFixture $cheapProducts,
        FailureProductsFixture $failureProducts,
        RoundingProductsFixture $roundingProducts
    ) {
        $this->categoryFixture = $categoryFixture;
        $this->salesChannelFixture = $salesChannelFixture;
        $this->subscriptionFixture = $subscriptionFixture;
        $this->voucherFixture = $voucherFixture;
        $this->cheapProducts = $cheapProducts;
        $this->failureProducts = $failureProducts;
        $this->roundingProducts = $roundingProducts;
    }

    public function install(bool $onlySetupMode, bool $onlyDemoData): void
    {
        if ($onlySetupMode) {
            $this->installSetup();

            return;
        }

        if ($onlyDemoData) {
            $this->installDemoData();

            return;
        }

        // --------------------------------
        // default, install ALL
        $this->installSetup();
        $this->installDemoData();
    }

    private function installSetup(): void
    {
        $this->salesChannelFixture->install();
    }

    private function installDemoData(): void
    {
        // ------------------------------------------
        // categories
        $this->categoryFixture->install();
        // ------------------------------------------
        // products
        $this->subscriptionFixture->install();
        $this->voucherFixture->install();
        $this->cheapProducts->install();
        $this->failureProducts->install();
        $this->roundingProducts->install();
    }
}
