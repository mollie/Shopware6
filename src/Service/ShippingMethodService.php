<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ShippingMethodService
{
    /**
     * @var EntityRepository<EntityCollection<ShippingMethodEntity>>
     */
    private $shippingMethodRepository;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    /**
     * Creates a new instance of the shipping method repository.
     *
     * @param EntityRepository<EntityCollection<ShippingMethodEntity>> $shippingMethodRepository
     */
    public function __construct($shippingMethodRepository, AbstractShippingMethodRoute $shippingMethodRoute)
    {
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->shippingMethodRoute = $shippingMethodRoute;
    }

    public function getShippingMethodById(string $shippingMethodId, SalesChannelContext $salesChannelContext): ?ShippingMethodEntity
    {
        $criteria = (new Criteria([$shippingMethodId]))->addAssociation('prices');

        $result = $this->shippingMethodRepository->search($criteria, $salesChannelContext->getContext());

        /** @var null|ShippingMethodEntity */
        return $result->get($shippingMethodId);
    }

    public function getActiveShippingMethods(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        $orFilter = new OrFilter([
            new EqualsAnyFilter('availabilityRuleId', $salesChannelContext->getRuleIds()),
            new EqualsFilter('availabilityRuleId', null)
        ]
        );

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('salesChannels.id', $salesChannelContext->getSalesChannel()->getId()))
            ->addFilter($orFilter)
            ->addAssociation('prices')
            ->addAssociation('salesChannels')
        ;

        return $this->shippingMethodRoute->load($request, $salesChannelContext, $criteria)->getShippingMethods();
    }
}
