<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Router;

interface RouteBuilderInterface
{
    public function getReturnUrl(string $transactionId): string;

    public function getWebhookUrl(string $transactionId): string;
}
