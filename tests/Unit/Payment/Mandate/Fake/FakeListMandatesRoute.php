<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Mandate\Fake;

use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Payment\Mandate\Route\AbstractListMandatesRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeListMandatesRoute extends AbstractListMandatesRoute
{
    /** @var list<string> */
    private array $requestedCustomerIds = [];

    /**
     * @param ?\Throwable $failure the error the real route raises when the Mollie API is unreachable
     */
    public function __construct(
        private readonly MandateCollection $mandates = new MandateCollection(),
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function getDecorated(): AbstractListMandatesRoute
    {
        return $this;
    }

    public function list(string $customerId, SalesChannelContext $salesChannelContext): ListMandatesResponse
    {
        $this->requestedCustomerIds[] = $customerId;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ListMandatesResponse($this->mandates);
    }

    public function getCallCount(): int
    {
        return count($this->requestedCustomerIds);
    }
}
