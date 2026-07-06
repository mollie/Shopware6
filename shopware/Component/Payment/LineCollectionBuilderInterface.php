<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

interface LineCollectionBuilderInterface
{
    public function build(OrderEntity $order, OrderDeliveryCollection $deliveries, CurrencyEntity $currency, string $taxStatus): LineItemCollection;
}
