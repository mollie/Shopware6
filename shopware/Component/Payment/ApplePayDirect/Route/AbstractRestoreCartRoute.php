<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractRestoreCartRoute
{
    abstract public function getDecorated(): self;

    abstract public function restore(SalesChannelContext $salesChannelContext): RestoreCartResponse;
}
