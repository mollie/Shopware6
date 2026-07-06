<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\LineItemFilterInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LineCollectionBuilder implements LineCollectionBuilderInterface
{
    public function __construct(
        #[Autowire(service: LineItemFilter::class)]
        private readonly LineItemFilterInterface $lineItemFilter,
    ) {
    }

    public function build(OrderEntity $order, OrderDeliveryCollection $deliveries, CurrencyEntity $currency, string $taxStatus): LineItemCollection
    {
        $lineItemCollection = new LineItemCollection();

        $orderLineItems = $order->getLineItems();
        $shippingDiscountLabel = $orderLineItems !== null ? LineItem::resolveDeliveryDiscountLabel($orderLineItems) : null;

        if ($orderLineItems !== null) {
            $filteredLineItems = $orderLineItems->filter($this->lineItemFilter->isItemAllowed(...));
            foreach ($filteredLineItems as $lineItem) {
                $lineItemCollection->add(LineItem::fromOrderLine($lineItem, $currency, $taxStatus));
            }
        }

        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts()->getTotalPrice();
            if (round($shippingCosts, 2) === 0.0) {
                continue;
            }

            $descriptionOverride = $shippingCosts < 0 ? $shippingDiscountLabel : null;
            $lineItemCollection->add(LineItem::fromDelivery($delivery, $currency, $taxStatus, $descriptionOverride));
        }

        return $lineItemCollection;
    }
}
