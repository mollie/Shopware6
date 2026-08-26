<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect\Fake;

use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FakeShippingMethodRoute extends AbstractShippingMethodRoute
{
    /** @var list<Request> */
    private array $requests = [];

    public function __construct(private readonly ShippingMethodCollection $shippingMethods = new ShippingMethodCollection())
    {
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        return $this;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $this->requests[] = $request;

        return new ShippingMethodRouteResponse(new EntitySearchResult(
            'shipping_method',
            $this->shippingMethods->count(),
            $this->shippingMethods,
            null,
            $criteria,
            $context->getContext()
        ));
    }

    public function getLastRequest(): Request
    {
        $last = end($this->requests);

        if ($last === false) {
            throw new \RuntimeException('The shipping methods were never loaded.');
        }

        return $last;
    }
}
