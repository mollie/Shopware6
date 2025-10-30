<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractWebhookRoute
{
    abstract public function getDecorated(): self;

    abstract public function notify(string $transactionId, SalesChannelContext $salesChannelContext): WebhookRouteResponse;
}
