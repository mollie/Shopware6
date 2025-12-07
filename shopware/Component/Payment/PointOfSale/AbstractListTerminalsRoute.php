<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PointOfSale;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractListTerminalsRoute
{
    abstract public function getDecorated(): self;

    abstract public function list(SalesChannelContext $salesChannelContext): ListTerminalsResponse;
}
