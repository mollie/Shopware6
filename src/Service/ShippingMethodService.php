<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ShippingMethodService
{
    /**
     * @var EntityRepository
     */
    private $shippingMethodRepository;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    /**
     * Creates a new instance of the shipping method repository.
     *
     * @param EntityRepository $shippingMethodRepository
     */
    public function __construct($shippingMethodRepository, AbstractShippingMethodRoute $shippingMethodRoute)
    {
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->shippingMethodRoute = $shippingMethodRoute;
    }

    /**
     * @param string $shippingMethodId
     * @param SalesChannelContext $salesChannelContext
     *
     * @return null|ShippingMethodEntity
     */
    public function getShippingMethodById(string $shippingMethodId, SalesChannelContext $salesChannelContext): ?ShippingMethodEntity
    {
        $criteria = (new Criteria([$shippingMethodId]))->addAssociation('prices');

        $result = $this->shippingMethodRepository->search($criteria, $salesChannelContext->getContext());

        /** @var null|ShippingMethodEntity $element */
        $element = $result->get($shippingMethodId);

        return $element;
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     *
     * @return ShippingMethodCollection
     */
    public function getActiveShippingMethods(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        return $this->shippingMethodRoute->load($request, $salesChannelContext, new Criteria())->getShippingMethods();
    }
}
