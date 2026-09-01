<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\PaymentLink\Fake;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeLogoutRoute extends AbstractLogoutRoute
{
    /** @var list<string> */
    private array $loggedOutTokens = [];

    public function getDecorated(): AbstractLogoutRoute
    {
        return $this;
    }

    public function logout(SalesChannelContext $context, RequestDataBag $data): ContextTokenResponse
    {
        $this->loggedOutTokens[] = $context->getToken();

        return new ContextTokenResponse('new-token');
    }

    /**
     * @return list<string>
     */
    public function getLoggedOutTokens(): array
    {
        return $this->loggedOutTokens;
    }
}
