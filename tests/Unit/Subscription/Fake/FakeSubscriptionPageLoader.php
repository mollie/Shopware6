<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\Page\SubscriptionPage;
use Mollie\Shopware\Component\Subscription\Page\SubscriptionPageLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The page loader is injected by its concrete type. It is not final, so the fake extends it and
 * replaces the constructor - the real one pulls in five routes and services.
 */
final class FakeSubscriptionPageLoader extends SubscriptionPageLoader
{
    private int $callCount = 0;

    public function __construct(private SubscriptionPage $page = new SubscriptionPage())
    {
    }

    public function load(Request $request, SalesChannelContext $context): SubscriptionPage
    {
        ++$this->callCount;

        return $this->page;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}
