<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractSessionWebhookRoute
{
    abstract public function getDecorated(): self;

    abstract public function notify(string $webhookId, Request $request, Context $context): WebhookResponse;
}
