<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractFinishCheckoutRoute
{
    abstract public function getDecorated(): AbstractStartCheckoutRoute;
    abstract public function finishCheckout(SalesChannelContext $context): FinishCheckoutResponse;
}
