<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeContextSwitchRoute extends AbstractContextSwitchRoute
{
    public function getDecorated(): AbstractContextSwitchRoute
    {
        throw new \RuntimeException('not decorated');
    }

    public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        return new ContextTokenResponse('switch-token');
    }
}
