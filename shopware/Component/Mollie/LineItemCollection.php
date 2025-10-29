<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Framework\Struct\Collection;

final class LineItemCollection extends Collection
{
    public static function fromDeliveries(OrderDeliveryCollection $deliveryCollection): self
    {
    }
}
