<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PaymentFinalizerInterface
{
    /**
     * Returns the url Mollie has to send the shopper to.
     */
    public function finalize(Session $session, OrderEntity $order, SalesChannelContext $salesChannelContext): string;
}
