<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeShopwareAccountService extends AccountService
{
    private ?string $loggedInId = null;

    private ?\Throwable $failure = null;

    public function __construct()
    {
    }

    /**
     * The login Shopware refuses, e.g. for an inactive or deleted customer.
     */
    public function withFailure(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function loginById(string $id, SalesChannelContext $context): string
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->loggedInId = $id;

        return 'fake-token';
    }

    public function getLoggedInId(): ?string
    {
        return $this->loggedInId;
    }
}
