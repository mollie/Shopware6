<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Mandate\Fake;

use Mollie\Shopware\Component\Payment\Mandate\Route\AbstractRevokeMandateRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\RevokeMandateResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeRevokeMandateRoute extends AbstractRevokeMandateRoute
{
    /** @var list<array{customerId: string, mandateId: string}> */
    private array $revoked = [];

    /**
     * @param ?\Throwable $failure the error the real route raises when one-click payments are off
     *                             or the mandate does not belong to the customer
     */
    public function __construct(
        private readonly bool $success = true,
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function getDecorated(): AbstractRevokeMandateRoute
    {
        return $this;
    }

    public function revoke(string $customerId, string $mandateId, SalesChannelContext $salesChannelContext): RevokeMandateResponse
    {
        $this->revoked[] = ['customerId' => $customerId, 'mandateId' => $mandateId];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new RevokeMandateResponse($this->success);
    }

    /**
     * @return list<array{customerId: string, mandateId: string}>
     */
    public function getRevoked(): array
    {
        return $this->revoked;
    }
}
