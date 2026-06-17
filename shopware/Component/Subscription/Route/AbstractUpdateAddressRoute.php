<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractUpdateAddressRoute
{
    public const TYPE_BILLING = 'billing';
    public const TYPE_SHIPPING = 'shipping';

    abstract public function getDecorated(): self;

    abstract public function updateBilling(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdateAddressResponse;

    abstract public function updateShipping(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdateAddressResponse;
}
