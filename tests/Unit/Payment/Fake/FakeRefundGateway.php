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

    /** @var list<array{paymentId: string, refundId: string}> */
    private array $cancelledRefunds = [];

    private ?Refund $refund = null;

    private ?RefundCollection $refundList = null;

    /**
     * The refund Mollie answers the create call with. Mollie returns a new resource with its own
     * id, status and amount, so the answer must not be derived from the request payload.
     */
    public function withRefund(Refund $refund): void
    {
        $this->refund = $refund;
    }

    public function createRefund(CreateRefund $createRefund, string $orderNumber, string $salesChannelId): Refund
    {
        $this->createdRefunds[] = $createRefund;

        if ($this->refund !== null) {
            return $this->refund;
        }

        return new Refund('re_fake', 'tr_fake', RefundStatus::Pending, new Money(0.0, 'EUR'), '', new \DateTimeImmutable('2020-01-01T00:00:00+00:00'));
    }

    public function cancelRefund(string $paymentId, string $refundId, string $orderNumber, string $salesChannelId): void
    {
        $this->cancelledRefunds[] = ['paymentId' => $paymentId, 'refundId' => $refundId];
    }

    /**
     * @return list<array{paymentId: string, refundId: string}>
     */
    public function getCancelledRefunds(): array
    {
        return $this->cancelledRefunds;
    }

    /**
     * The refunds Mollie reports for the payment. Needed to find the one that belongs to a return.
     */
    public function withRefundList(RefundCollection $refunds): void
    {
        $this->refundList = $refunds;
    }

    public function listRefunds(string $paymentId, string $orderNumber, string $salesChannelId): RefundCollection
    {
        return $this->refundList ?? new RefundCollection();
    }

    /**
     * @return list<CreateRefund>
     */
    public function getCreatedRefunds(): array
    {
        return $this->createdRefunds;
    }
}
