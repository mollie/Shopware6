<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ShippingMethodService
{
    /** @var EntityRepositoryInterface */
    private $shippingMethodRepository;

    /**
     * Creates a new instance of the shipping method repository.
     *
     * @param EntityRepositoryInterface $shippingMethodRepository
     */
    public function __construct(
        EntityRepositoryInterface $shippingMethodRepository
    )
    {
        $this->shippingMethodRepository = $shippingMethodRepository;
    }

    /**
     * @param string              $shippingMethodId
     * @param SalesChannelContext $salesChannelContext
     *
     * @return ShippingMethodEntity|null
     */
    public function getShippingMethodById(string $shippingMethodId, SalesChannelContext $salesChannelContext): ?ShippingMethodEntity
    {
        $criteria = (new Criteria([$shippingMethodId]))
            ->addAssociation('prices');

        return $this->shippingMethodRepository
            ->search($criteria, $salesChannelContext->getContext())
            ->get($shippingMethodId);
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     *
     * @return ShippingMethodCollection
     */
    public function getShippingMethods(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('salesChannels.id', $salesChannelContext->getSalesChannel()->getId()))
            ->addFilter(new EqualsAnyFilter('availabilityRuleId', $salesChannelContext->getRuleIds()))
            ->addAssociation('prices')
            ->addAssociation('salesChannels');

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodRepository
            ->search($criteria, $salesChannelContext->getContext())
            ->getEntities();

        return $shippingMethods->filterByActiveRules($salesChannelContext);
    }

    /**
     * Returns an array of shipping methods for Apple Pay Direct
     * @param SalesChannelContext $context
     *
     * @return array
     */
    public function getShippingMethodsForApplePayDirect(SalesChannelContext $context): array
    {
        /** array $shippingMethods */
        $shippingMethods = [];

        /** @var ShippingMethodCollection $availableShippingMethods */
        $availableShippingMethods = $this->getShippingMethods($context);

        /** @var float $shippingCost */
        $shippingCost = 0.0;

        if (
            $availableShippingMethods !== null
            && $availableShippingMethods->count()
        ) {
            foreach ($availableShippingMethods as $shippingMethod) {
                if (
                    $shippingMethod->getPrices()->count()
                    && $shippingMethod->getPrices()->first() !== null
                ) {
                    /** @var ShippingMethodPriceEntity $price */
                    $price = $shippingMethod->getPrices()->first();

                    if (method_exists($price, 'getCurrencyPrice')) {
                        /** @var PriceCollection $priceCollection */
                        $priceCollection = $price->getCurrencyPrice($context->getCurrency()->getId());

                        if ($priceCollection->count() && $priceCollection->first() !== null) {
                            if ($context->getTaxState() === CartPrice::TAX_STATE_FREE) {
                                $shippingCost = $priceCollection->first()->getNet();
                            } else {
                                $shippingCost = $priceCollection->first()->getGross();
                            }
                        }
                    } else {
                        $shippingCost = $price->getPrice();
                    }
                }

                $detail = '';

                if (
                    $shippingMethod->getDeliveryTime() !== null
                ) {
                    $detail = $shippingMethod->getDeliveryTime()->getName();
                }

                $shippingMethods[] = [
                    'label' => $shippingMethod->getName(),
                    'amount' => $shippingCost,
                    'detail' => $shippingMethod->getDescription() . ($detail !== '' ? ' (' . $detail . ')' : ''),
                    'identifier' => $shippingMethod->getId(),
                ];
            }
        }

        usort($shippingMethods, function($a, $b) {
            return $a['amount'] - $b['amount'];
        });

        return $shippingMethods;
    }
}
