<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractRenewRoute
{
    abstract public function getDecorated(): self;

    abstract public function renew(string $subscriptionId, Request $request, Context $context): WebhookResponse;
}