<?php

namespace Kiener\MolliePayments\Components\RefundManager\Request;

use Shopware\Core\Checkout\Order\OrderEntity;

class RefundRequest
{
    /**
     * @var string
     */
    private $orderNumber;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $internalDescription;

    /**
     * @var ?float
     */
    private $amount;

    /**
     * @var RefundRequestItem[]
     */
    private $items;



    /**
     * @param string $orderNumber
     * @param string $description
     * @param string $internalDescription
     * @param null|float $amount
     */
    public function __construct(string $orderNumber, string $description, string $internalDescription, ?float $amount)
    {
        $this->orderNumber = $orderNumber;
        $this->description = $description;
        $this->internalDescription = $internalDescription;
        $this->amount = $amount;
        $this->items = [];
    }

    /**
     * @param RefundRequestItem $item
     * @return void
     */
    public function addItem(RefundRequestItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @param RefundRequestItem[] $items
     * @return void
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * @return string
     */
    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        # i dont know why, but swagger only sends "," if nothing is provided
        # this must not happen in production anyway, so lets just skip that :)
        if (strlen(trim($this->description)) === 0 || $this->description === ',') {
            return "Refunded through Shopware. Order number: " . $this->orderNumber;
        }

        return $this->description;
    }

    /**
     * @return string
     */
    public function getInternalDescription(): string
    {
        return $this->internalDescription;
    }

    /**
     * @return ?float
     */
    public function getAmount(): ?float
    {
        return $this->amount;
    }

    /**
     * @return RefundRequestItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }


    /**
     * @return bool
     */
    public function isFullRefundAmountOnly(): bool
    {
        return ($this->amount === null && !$this->hasRefundableItemInstructions());
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function isFullRefundWithItems(OrderEntity $order): bool
    {
        if (!$this->hasRefundableItemInstructions()) {
            return false;
        }


        $itemsDifferToCartAmount = $this->isDifferentAmount($order);

        # then its no full refund
        if ($itemsDifferToCartAmount) {
            return false;
        }

        # now also check if we might have full item values but a different total amount
        if ($this->amount !== null && $this->amount > 0 && $this->amount !== $order->getAmountTotal()) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isPartialAmountOnly(): bool
    {
        if ($this->amount === null) {
            return false;
        }

        if ($this->hasRefundableItemInstructions()) {
            return false;
        }

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function isPartialAmountWithItems(OrderEntity $order): bool
    {
        if ($this->amount === null) {
            return false;
        }

        if (!$this->hasRefundableItemInstructions()) {
            return false;
        }

        $itemsDifferToCartAmount = $this->isDifferentAmount($order);

        if (!$itemsDifferToCartAmount) {
            # now also check if we might have full item values
            # but a different total amount
            if ($this->amount > 0 && $this->amount !== $order->getAmountTotal()) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * @param OrderEntity $order
     * @return bool
     */
    private function isDifferentAmount(OrderEntity $order): bool
    {
        $isDifferentAmount = false;

        /** @var RefundRequestItem $item */
        foreach ($this->items as $item) {
            if ($item->getQuantity() <= 0 && $item->getAmount() <= 0) {
                continue;
            }

            if ($order->getLineItems() !== null) {
                foreach ($order->getLineItems() as $orderItem) {
                    if ($orderItem->getId() === $item->getLineId()) {
                        if ($orderItem->getUnitPrice() !== $item->getAmount()) {
                            $isDifferentAmount = true;
                            break;
                        }
                    }
                }
            }

            if ($order->getDeliveries() !== null) {
                foreach ($order->getDeliveries() as $deliveryItem) {
                    if ($deliveryItem->getId() === $item->getLineId()) {
                        if ($deliveryItem->getShippingCosts()->getTotalPrice() !== $item->getAmount()) {
                            $isDifferentAmount = true;
                            break;
                        }
                    }
                }
            }
        }

        return $isDifferentAmount;
    }

    /**
     * @return bool
     */
    private function hasRefundableItemInstructions(): bool
    {
        foreach ($this->items as $item) {
            if ($item->getQuantity() > 0 || $item->getAmount() > 0) {
                return true;
            }
        }

        return false;
    }
}
