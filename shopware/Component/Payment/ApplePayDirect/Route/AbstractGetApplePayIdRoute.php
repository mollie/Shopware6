<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractGetApplePayIdRoute
{
    abstract public function getDecorated(): self;

    abstract public function getId(SalesChannelContext $salesChannelContext): GetApplePayIdResponse;
}
