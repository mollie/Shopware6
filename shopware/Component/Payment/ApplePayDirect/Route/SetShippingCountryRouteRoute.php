<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class SetShippingCountryRouteRoute extends AbstractSetShippingCountryRoute
{
    /**
     * @param EntityRepository<CountryCollection<CountryEntity>> $countryRepository
     */
    public function __construct(
        #[Autowire(service: ContextSwitchRoute::class)]
        private AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'country.repository')]
        private EntityRepository $countryRepository,
    ) {
    }

    public function getDecorated(): AbstractSetShippingCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.set-shipping-methods', path: '/store-api/mollie/applepay/shipping-method', methods: ['POST'])]
    public function setShippingCountry(Request $request, SalesChannelContext $salesChannelContext): SetShippingCountryResponse
    {
        $countryCode = $request->get('countryCode');

        $countryId = $this->getCountryId($countryCode, $salesChannelContext);
        if ($countryId === null) {
            throw ApplePayDirectException::invalidCountryCode($countryCode);
        }

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::COUNTRY_ID, $countryId);
        $contextSwitchResponse = $this->contextSwitchRoute->switchContext($requestDataBag, $salesChannelContext);

        $salesChannelContextServiceParameters = new SalesChannelContextServiceParameters($salesChannelContext->getSalesChannelId(), $contextSwitchResponse->getToken(), originalContext: $salesChannelContext->getContext());
        $newContext = $this->salesChannelContextService->get($salesChannelContextServiceParameters);

        return new SetShippingCountryResponse($newContext);
    }

    private function getCountryId(string $countryCode, SalesChannelContext $salesChannelContext): ?string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(new EqualsFilter('active', 1));
        $criteria->addFilter(new EqualsFilter('shippingAvailable', 1));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelContext->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('iso', $countryCode));

        $searchResult = $this->countryRepository->searchIds($criteria, $salesChannelContext->getContext());

        return $searchResult->firstId();
    }
}
