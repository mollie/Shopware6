<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Mollie\Tracking;
use Shopware\Core\Framework\Context;

final class OrderShippedEvent
{
    private ShippingItemCollection $shippingItems;

    private ?Tracking $tracking = null;

    public function __construct(private string $transactionId, private Context $context)
    {
        $this->shippingItems = new ShippingItemCollection();
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getShippingItems(): ShippingItemCollection
    {
        return $this->shippingItems;
    }

    public function setShippingItems(ShippingItemCollection $shippingItems): void
    {
        $this->shippingItems = $shippingItems;
    }

    public function getTracking(): ?Tracking
    {
        return $this->tracking;
    }

    public function setTracking(?Tracking $tracking): void
    {
        $this->tracking = $tracking;
    }
}
