<?php
declare(strict_types=1);

<<<<<<<< HEAD:shopware/Component/Payment/Mandate/Route/AbstractListMandatesRoute.php
namespace Mollie\Shopware\Component\Payment\Mandate\Route;
========
namespace Mollie\Shopware\Component\Payment\Mandate;
>>>>>>>> 8c770ca6 (add terminals and refactor some classes):shopware/Component/Payment/Mandate/AbstractListMandatesRoute.php

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractListMandatesRoute
{
    abstract public function getDecorated(): self;

    abstract public function list(string $customerId, SalesChannelContext $salesChannelContext): ListMandatesResponse;
}
