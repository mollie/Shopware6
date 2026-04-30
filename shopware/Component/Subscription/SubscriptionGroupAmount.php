<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Order\OrderEntity;

final class SubscriptionGroupAmount
{
    public function __construct(
        private readonly float $gross,
        private readonly float $net,
        private readonly string $taxStatus,
    ) {
    }

    public static function fromGroupCart(SubscriptionGroupCart $groupCart): self
    {
        $price = $groupCart->getCart()->getPrice();

        return new self(
            $price->getTotalPrice(),
            $price->getNetPrice(),
            $price->getTaxStatus(),
        );
    }

    public static function fromOrder(OrderEntity $order): self
    {
        return new self(
            $order->getAmountTotal(),
            $order->getAmountNet(),
            (string) $order->getTaxStatus(),
        );
    }

    public static function fromGroupCartOrOrder(?SubscriptionGroupCart $groupCart, OrderEntity $order): self
    {
        if ($groupCart === null) {
            return self::fromOrder($order);
        }

        return self::fromGroupCart($groupCart);
    }

    public function gross(): float
    {
        return $this->gross;
    }

    public function net(): float
    {
        return $this->net;
    }

    public function getTaxStatus(): string
    {
        return $this->taxStatus;
    }
}
