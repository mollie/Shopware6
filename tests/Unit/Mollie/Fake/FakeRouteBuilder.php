<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;

final class FakeRouteBuilder implements RouteBuilderInterface
{
    public function __construct(private string $returnUrl = '', private string $webhookUrl = '',private string $posCheckoutUrl = '')
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

    public function getPosCheckoutUrl(Payment $payment, string $transactionId, string $orderNumber): string
    {
        return $this->posCheckoutUrl;
    }

    public function getPaypalExpressRedirectUrl(): string
    {
        // TODO: Implement getPaypalExpressRedirectUrl() method.
    }

    public function getPaypalExpressCancelUrl(): string
    {
        // TODO: Implement getPaypalExpressCancelUrl() method.
    }

}
