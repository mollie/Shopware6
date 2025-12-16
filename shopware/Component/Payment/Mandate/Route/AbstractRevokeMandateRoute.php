<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate\Route;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractRevokeMandateRoute
{
    abstract public function getDecorated(): self;

    abstract public function revoke(string $customerId, string $mandateId, SalesChannelContext $salesChannelContext): RevokeMandateResponse;
}
