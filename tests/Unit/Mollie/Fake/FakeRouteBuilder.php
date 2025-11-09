<?php
declare(strict_types=1);

namespace Mollie\Unit\Mollie\Fake;

use Mollie\Shopware\Component\Router\RouteBuilderInterface;

final class FakeRouteBuilder implements RouteBuilderInterface
{
    public function __construct(private string $returnUrl = '', private string $webhookUrl = '')
    {
    }

    public function getReturnUrl(string $transactionId): string
    {
        return $this->returnUrl;
    }

    public function getWebhookUrl(string $transactionId): string
    {
        return $this->webhookUrl;
    }
}
