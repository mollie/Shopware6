<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractUpdatePaymentMethodRoute
{
    abstract public function getDecorated(): self;

    abstract public function start(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdatePaymentMethodResponse;

    abstract public function confirm(string $subscriptionId, SalesChannelContext $context): UpdatePaymentMethodConfirmedResponse;
}
