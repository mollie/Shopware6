<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Event;

use Shopware\Core\Checkout\Cart\LineItem\LineItem as CheckoutCartLineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Class MollieSubscriptionCartItemAddedEvent
 *
 * This event is triggered when a product with a Mollie subscription is added to the cart.
 */
class MollieSubscriptionCartItemAddedEvent
{
    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var CheckoutCartLineItem
     */
    private $lineItem;

    /**
     * MollieSubscriptionCartItemAddedEvent constructor.
     *
     * @param SalesChannelContext $context The sales channel context
     * @param CheckoutCartLineItem $lineItem The line item added to the cart
     */
    public function __construct(SalesChannelContext $context, CheckoutCartLineItem $lineItem)
    {
        $this->context = $context;
        $this->lineItem = $lineItem;
    }

    /**
     * Get the sales channel context.
     *
     * @return SalesChannelContext The sales channel context
     */
    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    /**
     * Get the line item that was added to the cart.
     *
     * @return CheckoutCartLineItem The line item added to the cart
     */
    public function getLineItem(): CheckoutCartLineItem
    {
        return $this->lineItem;
    }
}
