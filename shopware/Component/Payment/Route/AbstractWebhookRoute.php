<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractWebhookRoute
{
    abstract public function getDecorated(): self;

    abstract public function notify(Request $request, Context $context): WebhookRouteResponse;
}
