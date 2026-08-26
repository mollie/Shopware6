<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Symfony\Component\HttpFoundation\Request;

final class FakeSalutationRoute extends AbstractSalutationRoute
{
    public function __construct(private readonly SalutationCollection $salutations = new SalutationCollection())
    {
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        return new SalutationRouteResponse(new EntitySearchResult(
            SalutationDefinition::ENTITY_NAME,
            $this->salutations->count(),
            $this->salutations,
            null,
            $criteria,
            Context::createDefaultContext()
        ));
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        return $this;
    }
}
