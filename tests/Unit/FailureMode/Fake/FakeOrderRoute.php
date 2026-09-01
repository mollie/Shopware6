<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FailureMode\Fake;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FakeOrderRoute extends AbstractOrderRoute
{
    public function __construct(private readonly ?OrderEntity $order = null)
    {
    }

    public function getDecorated(): AbstractOrderRoute
    {
        return $this;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        $orders = new OrderCollection($this->order === null ? [] : [$this->order]);

        return new OrderRouteResponse(new EntitySearchResult(
            'order',
            $orders->count(),
            $orders,
            null,
            $criteria,
            $context->getContext()
        ));
    }
}
