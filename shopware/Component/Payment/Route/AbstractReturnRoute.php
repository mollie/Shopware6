<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\Context;

abstract class AbstractReturnRoute
{
    abstract public function getDecorated(): self;

    abstract public function return(string $transactionId, Context $context): ReturnRouteResponse;
}
