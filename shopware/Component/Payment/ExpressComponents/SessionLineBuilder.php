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
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Converts a cart into the lines of a Mollie session.
 *
 * POST /v2/sessions rejects a payload whose lines do not add up to the amount, and it
 * validates the transmitted, rounded values. Unlike the Orders API payload the rounding
 * correction is therefore not optional here.
 *
 * The deliveries of the cart are deliberately left out: the session carries them as
 * shippingOptions instead, and Mollie rejects a payload that has both
 * ("A shipping_fee line is not allowed when shippingOptions are provided"). The amount
 * passed in therefore has to be the cart total without shipping.
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

    public function build(Cart $cart, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection
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

        return $this->roundingDifferenceFixer->fixAmountDiff(
            $amount,
            $lines,
            RoundingDifferenceFixer::DEFAULT_TITLE,
            RoundingDifferenceFixer::SKU
        );
    }
}
