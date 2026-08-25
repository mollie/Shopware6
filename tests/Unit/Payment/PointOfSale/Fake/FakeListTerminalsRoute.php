<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PointOfSale\Fake;

use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\AbstractListTerminalsRoute;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\ListTerminalsResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeListTerminalsRoute extends AbstractListTerminalsRoute
{
    private int $callCount = 0;

    public function __construct(private readonly TerminalCollection $terminals = new TerminalCollection())
    {
    }

    public function getDecorated(): AbstractListTerminalsRoute
    {
        return $this;
    }

    public function list(SalesChannelContext $salesChannelContext): ListTerminalsResponse
    {
        ++$this->callCount;

        return new ListTerminalsResponse($this->terminals);
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}
