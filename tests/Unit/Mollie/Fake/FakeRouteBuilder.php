<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;

final class FakeRouteBuilder implements RouteBuilderInterface
{
    public function __construct(
        private string $returnUrl = '',
        private string $webhookUrl = '',
        private string $posCheckoutUrl = '',
        private string $subscriptionWebhookUrl = '',
        private string $subscriptionPaymentUpdateReturnUrl = '',
        private string $subscriptionPaymentUpdateWebhookUrl = '',
        private string $expressComponentsRedirectUrl = '',
        private string $expressComponentsShippingCallbackUrl = '',
        private string $checkoutFinishUrl = '',
        private string $editOrderUrl = '',
    ) {
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

    public function getExpressComponentsRedirectUrl(string $cartToken): string
    {
        return $this->expressComponentsRedirectUrl;
    }

    public function getExpressComponentsOrderRedirectUrl(string $orderId): string
    {
        return $this->expressComponentsRedirectUrl;
    }

    public function getExpressComponentsShippingCallbackUrl(string $salesChannelId, string $cartToken): string
    {
        return $this->expressComponentsShippingCallbackUrl;
    }

    public function getCheckoutFinishUrl(string $orderId): string
    {
        return $this->checkoutFinishUrl;
    }

    public function getEditOrderUrl(string $orderId): string
    {
        return $this->editOrderUrl;
    }

    public function getSubscriptionWebhookUrl(string $subscriptionId): string
    {
        return $this->subscriptionWebhookUrl;
    }

    public function getSubscriptionPaymentUpdateReturnUrl(string $subscriptionId): string
    {
        return $this->subscriptionPaymentUpdateReturnUrl;
    }

    public function getSubscriptionPaymentUpdateWebhookUrl(string $subscriptionId): string
    {
        return $this->subscriptionPaymentUpdateWebhookUrl;
    }
}
