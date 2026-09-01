<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSalesChannelContextService implements SalesChannelContextServiceInterface
{
    /** @var list<SalesChannelContextServiceParameters> */
    private array $requested = [];

    public function __construct(private readonly SalesChannelContext $context)
    {
    }

    public function get(SalesChannelContextServiceParameters $parameters): SalesChannelContext
    {
        $this->requested[] = $parameters;

        return $this->context;
    }

    public function getLastParameters(): SalesChannelContextServiceParameters
    {
        $last = end($this->requested);

        if ($last === false) {
            throw new \RuntimeException('No sales channel context was rebuilt.');
        }

        return $last;
    }
}
