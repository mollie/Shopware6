<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface TempAddressManagerInterface
{
    public function apply(RequestDataBag $requestDataBag, TempAddress $tempAddress, SalesChannelContext $salesChannelContext): RequestDataBag;

    public function restore(SalesChannelContext $originalContext): void;
}
