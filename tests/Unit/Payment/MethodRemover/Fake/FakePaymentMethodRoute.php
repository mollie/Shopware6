<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover\Fake;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FakePaymentMethodRoute extends AbstractPaymentMethodRoute
{
    public function __construct(private PaymentMethodCollection $paymentMethods)
    {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        /** @var EntitySearchResult<PaymentMethodCollection> $searchResult */
        $searchResult = new EntitySearchResult(
            'payment_method',
            $this->paymentMethods->count(),
            $this->paymentMethods,
            null,
            $criteria,
            $context->getContext()
        );

        return new PaymentMethodRouteResponse($searchResult);
    }
}
