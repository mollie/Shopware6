<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Router;

use Mollie\Shopware\Component\Mollie\Payment;

interface RouteBuilderInterface
{
    public function getReturnUrl(string $transactionId): string;

    public function getWebhookUrl(string $transactionId): string;

    public function getPosCheckoutUrl(Payment $payment,string $transactionId, string $orderNumber): string;

    public function getPaypalExpressRedirectUrl(): string;

    public function getPaypalExpressCancelUrl(): string;
}
