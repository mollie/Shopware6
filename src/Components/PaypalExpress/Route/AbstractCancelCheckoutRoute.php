<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Mollie\Shopware\Component\Payment\PayPalExpress\AbstractStartCheckoutRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractCancelCheckoutRoute
{
    abstract public function getDecorated(): AbstractStartCheckoutRoute;

    abstract public function cancelCheckout(SalesChannelContext $context): CancelCheckoutResponse;
}
