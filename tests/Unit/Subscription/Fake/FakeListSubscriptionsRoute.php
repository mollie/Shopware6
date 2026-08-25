<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\AbstractListSubscriptionsRoute;
use Mollie\Shopware\Component\Subscription\Route\SubscriptionsListResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FakeListSubscriptionsRoute extends AbstractListSubscriptionsRoute
{
    public function __construct(private readonly SubscriptionCollection $subscriptions = new SubscriptionCollection())
    {
    }

    public function getDecorated(): AbstractListSubscriptionsRoute
    {
        return $this;
    }

    public function list(Request $request, SalesChannelContext $context): SubscriptionsListResponse
    {
        return new SubscriptionsListResponse(new EntitySearchResult(
            SubscriptionEntity::class,
            $this->subscriptions->count(),
            $this->subscriptions,
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));
    }
}
