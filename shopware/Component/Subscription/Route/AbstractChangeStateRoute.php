<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractChangeStateRoute
{
    abstract public function getDecorated(): self;

    abstract public function changeState(string $subscriptionId, Request $request, SalesChannelContext $context): ChangeStateResponse;
}
