<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FakeCountryRoute extends AbstractCountryRoute
{
    /** @var list<Criteria> */
    private array $criteria = [];

    public function __construct(private readonly CountryCollection $countries = new CountryCollection())
    {
    }

    public function load(Request $request, Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $this->criteria[] = $criteria;

        return new CountryRouteResponse(new EntitySearchResult(
            CountryDefinition::ENTITY_NAME,
            $this->countries->count(),
            $this->countries,
            null,
            $criteria,
            Context::createDefaultContext()
        ));
    }

    /**
     * @return list<Criteria>
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    protected function getDecorated(): AbstractCountryRoute
    {
        return $this;
    }
}
