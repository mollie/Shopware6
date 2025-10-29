<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractWebhookRoute
{
    abstract public function getDecorated(): self;

    abstract public function notify(Request $request, SalesChannelContext $context): WebhookRouteResponse;
}
