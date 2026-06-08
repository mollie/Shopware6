<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Order
{
    private LineItemCollection $lines;
    private RefundCollection $refunds;
    private ?Money $amountCaptured = null;
    private ?Payment $payment = null;

    /** @param LineItem[] $lines */
    public function __construct(
        private readonly string $id,
        private readonly string $checkoutUrl,
        array $lines = [],
    ) {
        $this->lines = new LineItemCollection($lines);
        $this->refunds = new RefundCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }

    public function getLines(): LineItemCollection
    {
        return $this->lines;
    }

    public function getRefunds(): RefundCollection
    {
        return $this->refunds;
    }

    public function getAmountCaptured(): ?Money
    {
        return $this->amountCaptured;
    }

    public function getPayment(): Payment
    {
        if ($this->payment === null) {
            throw new \RuntimeException('Mollie order has no payment');
        }

        return $this->payment;
    }

    public function withPayment(Payment $payment): self
    {
        $clone = clone $this;
        $clone->payment = $payment;

        return $clone;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $checkoutUrl = $body['_links']['checkout']['href'] ?? '';
        $embeddedPaymentBody = $body['_embedded']['payments'][0] ?? null;

        if ($embeddedPaymentBody === null) {
            throw new \RuntimeException(sprintf('Mollie order "%s" has no embedded payment in API response', $body['id'] ?? 'unknown'));
        }

        $lines = [];
        foreach ($body['lines'] ?? [] as $line) {
            $lines[] = LineItem::createFromClientResponse($line);
        }

        $order = new self($body['id'] ?? '', $checkoutUrl, $lines);
        $order->payment = Payment::createFromClientResponse($embeddedPaymentBody);

        foreach ($body['_embedded']['refunds'] ?? [] as $refundData) {
            $order->refunds->add(Refund::createFromClientResponse($refundData));
        }

        if (isset($body['amountCaptured']['value'], $body['amountCaptured']['currency'])) {
            $order->amountCaptured = new Money((float) $body['amountCaptured']['value'], (string) $body['amountCaptured']['currency']);
        }

        return $order;
    }
}
