<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Refund;
use Mollie\Shopware\Component\Mollie\RefundCollection;

interface RefundGatewayInterface
{
    public function createRefund(CreateRefund $createRefund, string $orderNumber, string $salesChannelId): Refund;

    public function cancelRefund(string $paymentId, string $refundId, string $orderNumber, string $salesChannelId): void;

    public function listRefunds(string $paymentId, string $orderNumber, string $salesChannelId): RefundCollection;
}
