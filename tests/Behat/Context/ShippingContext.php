<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Step\Given;
use Mollie\Shopware\Integration\Data\ShippingMethodTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

final class ShippingContext extends ShopwareContext
{
    use ShippingMethodTestBehaviour;

    #[Given('i select :arg1 as shipping method')]
    public function iSelectAsShippingMethod(string $technicalName): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $shippingMethod = $this->getShippingMethodByTechnicalName($technicalName, $context);
        $this->activateShippingMethod($shippingMethod, $context);
        $this->assignShippingMethodToSalesChannel($shippingMethod, $salesChannelContext->getSalesChannel(), $context);

        $this->setOptions(SalesChannelContextService::SHIPPING_METHOD_ID, $shippingMethod->getId());
    }
}
