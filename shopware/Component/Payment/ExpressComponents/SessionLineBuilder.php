<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\LineItemFilterInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixerInterface;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Converts a cart into the lines of a Mollie session.
 *
 * POST /v2/sessions rejects a payload whose lines do not add up to the amount, and it
 * validates the transmitted, rounded values. Unlike the Orders API payload the rounding
 * correction is therefore not optional here.
 */
final class SessionLineBuilder implements SessionLineBuilderInterface
{
    public function __construct(
        #[Autowire(service: LineItemFilter::class)]
        private LineItemFilterInterface $lineItemFilter,
        #[Autowire(service: RoundingDifferenceFixer::class)]
        private RoundingDifferenceFixerInterface $roundingDifferenceFixer
    ) {
    }

    public function build(Cart $cart, Money $amount, bool $withShippingLines, SalesChannelContext $salesChannelContext): LineItemCollection
    {
        $currency = $salesChannelContext->getCurrency();
        $taxStatus = $cart->getPrice()->getTaxStatus();

        $lines = new LineItemCollection();

        // a cart only exposes the top level line items, children hang off their parent. The flat
        // list reproduces the shape of an order, so the existing filter behaves identically.
        foreach ($cart->getLineItems()->getFlat() as $cartLineItem) {
            if (! $this->lineItemFilter->isItemAllowed($cartLineItem)) {
                continue;
            }

            $lines->add(LineItem::fromCartLineItem($cartLineItem, $currency, $taxStatus));
        }

        $lines = $this->addCartShippingLines($lines, $cart, $withShippingLines, $currency, $taxStatus);

        return $this->fixRoundingDiff($amount, $lines);
    }

    /**
     * On the edit order page there is no cart, the lines come from the order instead. Order line
     * items are already a flat list, so unlike the cart they need no getFlat().
     */
    public function buildFromOrder(OrderEntity $order, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection
    {
        $currency = $salesChannelContext->getCurrency();
        $taxStatus = (string) $order->getTaxStatus();

        $lines = new LineItemCollection();

        $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        foreach ($orderLineItems as $orderLineItem) {
            if (! $this->lineItemFilter->isItemAllowed($orderLineItem, $orderLineItems)) {
                continue;
            }

            $lines->add(LineItem::fromOrderLine($orderLineItem, $currency, $taxStatus));
        }

        $shippingDiscountLabel = LineItem::resolveDeliveryDiscountLabel($orderLineItems);

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $shippingCosts = $delivery->getShippingCosts()->getTotalPrice();
            if (round($shippingCosts, Mollie::ROUNDING_PRECISION) === 0.0) {
                continue;
            }

            $descriptionOverride = $shippingCosts < 0 ? $shippingDiscountLabel : null;
            $lines->add(LineItem::fromDelivery($delivery, $currency, $taxStatus, $descriptionOverride));
        }

        return $this->fixRoundingDiff($amount, $lines);
    }

    private function addCartShippingLines(LineItemCollection $lines, Cart $cart, bool $withShippingLines, CurrencyEntity $currency, string $taxStatus): LineItemCollection
    {
        if (! $withShippingLines) {
            return $lines;
        }

        foreach ($cart->getDeliveries() as $delivery) {
            if (round($delivery->getShippingCosts()->getTotalPrice(), Mollie::ROUNDING_PRECISION) === 0.0) {
                continue;
            }

            $lines->add(LineItem::fromCartDelivery($delivery, $currency, $taxStatus));
        }

        return $lines;
    }

    private function fixRoundingDiff(Money $amount, LineItemCollection $lines): LineItemCollection
    {
        return $this->roundingDifferenceFixer->fixAmountDiff(
            $amount,
            $lines,
            RoundingDifferenceFixer::DEFAULT_TITLE,
            RoundingDifferenceFixer::SKU
        );
    }
}
