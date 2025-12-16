<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractListMandatesRoute
{
    abstract public function getDecorated(): self;

    abstract public function list(string $customerId, SalesChannelContext $salesChannelContext): ListMandatesResponse;
}
