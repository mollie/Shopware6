<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractReturnRoute
{
    abstract public function getDecorated(): self;

    abstract public function return(Request $request, Context $context): ReturnRouteResponse;
}
