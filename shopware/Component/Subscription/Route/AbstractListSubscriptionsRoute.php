<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractListSubscriptionsRoute
{
    abstract public function getDecorated(): self;

    abstract public function list(Request $request, SalesChannelContext $context): SubscriptionsListResponse;
}
