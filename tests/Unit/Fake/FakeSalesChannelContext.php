<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSalesChannelContext extends SalesChannelContext
{
    public function __construct(private string $fakeSalesChannelId)
    {
    }

    public function getSalesChannelId(): string
    {
        return $this->fakeSalesChannelId;
    }
}
