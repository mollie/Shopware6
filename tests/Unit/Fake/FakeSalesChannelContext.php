<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSalesChannelContext extends SalesChannelContext
{
    private string $fakeSalesChannelId;
    private string $fakeToken;
    private Context $fakeContext;

    public function __construct(
        string $salesChannelId = 'sales-channel-id',
        string $token = 'cart-token',
        ?Context $context = null,
    ) {
        $this->fakeSalesChannelId = $salesChannelId;
        $this->fakeToken = $token;
        $this->fakeContext = $context ?? Context::createDefaultContext();
    }

    public function getSalesChannelId(): string
    {
        return $this->fakeSalesChannelId;
    }

    public function getToken(): string
    {
        return $this->fakeToken;
    }

    public function getContext(): Context
    {
        return $this->fakeContext;
    }
}
