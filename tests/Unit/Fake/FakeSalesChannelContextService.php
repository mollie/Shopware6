<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSalesChannelContextService implements SalesChannelContextServiceInterface
{
    public function __construct(private readonly SalesChannelContext $context)
    {
    }

    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext
    {
        return $this->context;
    }
}
