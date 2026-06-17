<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeShopwareAccountService extends AccountService
{
    private ?string $loggedInId = null;

    public function __construct()
    {
    }

    public function loginById(string $id, SalesChannelContext $context): string
    {
        $this->loggedInId = $id;

        return 'fake-token';
    }

    public function getLoggedInId(): ?string
    {
        return $this->loggedInId;
    }
}
