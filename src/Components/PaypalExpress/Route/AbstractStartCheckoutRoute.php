<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractStartCheckoutRoute
{
    abstract public function getDecorated(): AbstractStartCheckoutRoute;

    abstract public function startCheckout(Request $request, SalesChannelContext $context): StartCheckoutResponse;
}
