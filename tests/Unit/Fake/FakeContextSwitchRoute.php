<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeContextSwitchRoute extends AbstractContextSwitchRoute
{
    /** @var list<array<string, mixed>> */
    private array $switches = [];

    public function getDecorated(): AbstractContextSwitchRoute
    {
        throw new \RuntimeException('not decorated');
    }

    public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $this->switches[] = $data->all();

        return new ContextTokenResponse('switch-token');
    }

    /**
     * What the caller asked Shopware to switch - the country and the shipping method it sets are
     * what the following cart calculation depends on.
     *
     * @return list<array<string, mixed>>
     */
    public function getSwitches(): array
    {
        return $this->switches;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastSwitch(): array
    {
        $last = end($this->switches);

        if ($last === false) {
            throw new \RuntimeException('The context was never switched.');
        }

        return $last;
    }
}
