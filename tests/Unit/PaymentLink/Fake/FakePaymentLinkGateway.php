<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\PaymentLink\Fake;

use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGatewayInterface;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentLink;

final class FakePaymentLinkGateway implements PaymentLinkGatewayInterface
{
    /** @var list<CreatePaymentLink> */
    private array $createdPayloads = [];

    /** @var list<array{paymentLinkId: string, payload: CreatePaymentLink}> */
    private array $updatedPayloads = [];

    private PaymentCollection $payments;

    public function __construct(
        private PaymentLink $createdLink = new PaymentLink('pl_created', 'https://mollie.test/pl_created'),
        private PaymentLink $updatedLink = new PaymentLink('pl_existing', 'https://mollie.test/pl_existing'),
    ) {
        $this->payments = new PaymentCollection();
    }

    /**
     * The payments Mollie reports for an existing link - a settled one means there is nothing
     * left to pay.
     */
    public function withPayments(PaymentCollection $payments): void
    {
        $this->payments = $payments;
    }

    public function createPaymentLink(CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink
    {
        $this->createdPayloads[] = $createPaymentLink;

        return $this->createdLink;
    }

    public function updatePaymentLink(string $paymentLinkId, CreatePaymentLink $createPaymentLink, string $orderNumber, string $salesChannelId): PaymentLink
    {
        $this->updatedPayloads[] = ['paymentLinkId' => $paymentLinkId, 'payload' => $createPaymentLink];

        return $this->updatedLink;
    }

    public function getPaymentLinkPayments(string $paymentLinkId, string $orderNumber, string $salesChannelId): PaymentCollection
    {
        return $this->payments;
    }

    /**
     * @return list<CreatePaymentLink>
     */
    public function getCreatedPayloads(): array
    {
        return $this->createdPayloads;
    }

    /**
     * @return list<array{paymentLinkId: string, payload: CreatePaymentLink}>
     */
    public function getUpdatedPayloads(): array
    {
        return $this->updatedPayloads;
    }
}
