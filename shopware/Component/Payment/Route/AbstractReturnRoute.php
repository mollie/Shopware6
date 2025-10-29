<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractReturnRoute
{
    abstract public function getDecorated(): self;

    abstract public function return(string $transactionId, SalesChannelContext $context): ReturnRouteResponse;
}
