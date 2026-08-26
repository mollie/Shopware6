<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Fake;

use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\RefundBuilderInterface;
use Shopware\Core\Checkout\Order\OrderEntity;

final class FakeRefundBuilder implements RefundBuilderInterface
{
    /** @var list<array{items: array<mixed>, description: string, amount: null|float}> */
    private array $calls = [];

    private ?CreateRefund $createRefund = null;

    /**
     * The payload the builder should answer with, when the test cares about what reaches the
     * gateway. Without it the fake derives the amount from the request, like the real builder.
     */
    public function withCreateRefund(CreateRefund $createRefund): void
    {
        $this->createRefund = $createRefund;
    }

    public function build(Payment $payment, OrderEntity $order, array $requestItems, string $description, ?float $requestAmount = null): CreateRefund
    {
        $this->calls[] = [
            'items' => $requestItems,
            'description' => $description,
            'amount' => $requestAmount,
        ];

        if ($this->createRefund !== null) {
            return $this->createRefund;
        }

        return new CreatePaymentRefund(
            $payment->getId(),
            new Money($requestAmount ?? $order->getAmountTotal(), 'EUR'),
            $description
        );
    }

    /**
     * @return list<array{items: array<mixed>, description: string, amount: null|float}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return array{items: array<mixed>, description: string, amount: null|float}
     */
    public function getLastCall(): array
    {
        $last = end($this->calls);

        if ($last === false) {
            throw new \RuntimeException('The refund builder has not been called.');
        }

        return $last;
    }
}
