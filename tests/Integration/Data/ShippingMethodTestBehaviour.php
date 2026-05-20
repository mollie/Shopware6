<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait ShippingMethodTestBehaviour
{
    use IntegrationTestBehaviour;

    public function getShippingMethodByTechnicalName(string $technicalName, Context $context): ShippingMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        $first = $repository->search($criteria, $context)->first();
        if (! $first instanceof ShippingMethodEntity) {
            throw new \RuntimeException(sprintf('Shipping method not found for technical name "%s"', $technicalName));
        }

        return $first;
    }

    public function activateShippingMethod(ShippingMethodEntity $shippingMethod, Context $context): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('shipping_method.repository');
        $repository->upsert([['id' => $shippingMethod->getId(), 'active' => true]], $context);
    }

    private function assignShippingMethodToSalesChannel(ShippingMethodEntity $shippingMethod, SalesChannelEntity $salesChannel, Context $context): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('sales_channel_shipping_method.repository');
        $repository->upsert([
            [
                'salesChannelId' => $salesChannel->getId(),
                'shippingMethodId' => $shippingMethod->getId(),
            ]
        ], $context);
    }
}
