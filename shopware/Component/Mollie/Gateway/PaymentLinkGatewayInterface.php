<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentLink;

interface PaymentLinkGatewayInterface
{
    public function createPaymentLink(CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink;

    public function updatePaymentLink(string $paymentLinkId, CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink;

    public function getPaymentLinkPayments(string $paymentLinkId, string $orderNumber, string $salesChannelId): PaymentCollection;
}
