<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures;

use Kiener\MolliePayments\Components\Fixtures\Handler\Category\CategoryFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\PaymentMethod\PaymentMethodsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\CheapProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\FailureProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\RoundingProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\SubscriptionProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Product\VoucherProductsFixture;
use Kiener\MolliePayments\Components\Fixtures\Handler\Shipment\ShipmentFixture;

class FixturesInstaller
{
    private CategoryFixture $categoryFixture;
    private PaymentMethodsFixture $salesChannelFixture;
    private ShipmentFixture $shipmentFixture;
    private SubscriptionProductsFixture $subscriptionFixture;
    private VoucherProductsFixture $voucherFixture;
    private CheapProductsFixture $cheapProducts;
    private FailureProductsFixture $failureProducts;
    private RoundingProductsFixture $roundingProducts;

    public function __construct(
        CategoryFixture $categoryFixture,
        PaymentMethodsFixture $salesChannelFixture,
        ShipmentFixture $shipmentFixture,
        SubscriptionProductsFixture $subscriptionFixture,
        VoucherProductsFixture $voucherFixture,
        CheapProductsFixture $cheapProducts,
        FailureProductsFixture $failureProducts,
        RoundingProductsFixture $roundingProducts
    ) {
        $this->categoryFixture = $categoryFixture;
        $this->salesChannelFixture = $salesChannelFixture;
        $this->shipmentFixture = $shipmentFixture;
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

    public function uninstall(): void
    {
        $this->shipmentFixture->uninstall();
        $this->categoryFixture->uninstall();

        $this->cheapProducts->uninstall();
        $this->failureProducts->uninstall();
        $this->roundingProducts->uninstall();
        $this->voucherFixture->uninstall();
        $this->subscriptionFixture->uninstall();
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
        // shipment
        $this->shipmentFixture->install();
        // ------------------------------------------
        // products
        $this->subscriptionFixture->install();
        $this->voucherFixture->install();
        $this->cheapProducts->install();
        $this->failureProducts->install();
        $this->roundingProducts->install();
    }
}
