<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Refund;
use Mollie\Shopware\Component\Mollie\RefundCollection;
use Mollie\Shopware\Component\Mollie\RefundStatus;

final class FakeRefundGateway implements RefundGatewayInterface
{
    /** @var list<CreateRefund> */
    private array $createdRefunds = [];

    public function createRefund(CreateRefund $createRefund, string $orderNumber, string $salesChannelId): Refund
    {
        $this->createdRefunds[] = $createRefund;

        return new Refund('re_fake', 'tr_fake', RefundStatus::Pending, new Money(0.0, 'EUR'), '', new \DateTimeImmutable('2020-01-01T00:00:00+00:00'));
    }

    public function cancelRefund(string $paymentId, string $refundId, string $orderNumber, string $salesChannelId): void
    {
    }

    public function listRefunds(string $paymentId, string $orderNumber, string $salesChannelId): RefundCollection
    {
        return new RefundCollection();
    }

    /**
     * @return list<CreateRefund>
     */
    public function getCreatedRefunds(): array
    {
        return $this->createdRefunds;
    }
}
