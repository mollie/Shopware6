<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\Context;

abstract class AbstractWebhookRoute
{
    abstract public function getDecorated(): self;

    abstract public function notify(string $transactionId, Context $context): WebhookResponse;
}
