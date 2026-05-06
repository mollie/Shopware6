<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Page;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Route\AbstractListSubscriptionsRoute;
use Mollie\Shopware\Component\Subscription\Route\ListSubscriptionsRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPageLoader
{
    public function __construct(
        #[Autowire(service: GenericPageLoader::class)]
        private readonly GenericPageLoaderInterface $genericLoader,
        #[Autowire(service: ListSubscriptionsRoute::class)]
        private readonly AbstractListSubscriptionsRoute $listSubscriptionsRoute,
        #[Autowire(service: CountryRoute::class)]
        private readonly AbstractCountryRoute $countryRoute,
        #[Autowire(service: SalutationRoute::class)]
        private readonly AbstractSalutationRoute $salutationRoute,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): SubscriptionPage
    {
        /** @var SubscriptionPage $page */
        $page = $this->genericLoader->load($request, $salesChannelContext);
        $page = SubscriptionPage::createFrom($page);

        if ($page->getMetaInformation() instanceof MetaInformation) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $page->setSubscriptions(
            $this->listSubscriptionsRoute
                ->list($request, $salesChannelContext)
                ->getEntitySearchResult()
        );

        $page->setCountries(
            $this->countryRoute->load(new Request(), $this->buildCountryCriteria(), $salesChannelContext)->getCountries()
        );
        $page->setSalutations(
            $this->salutationRoute->load(new Request(), $salesChannelContext, new Criteria())->getSalutations()
        );

        $settings = $this->settingsService->getSubscriptionSettings($salesChannelContext->getSalesChannelId());
        $page->setAllowAddressEditing($settings->isAllowEditAddress());
        $page->setAllowPauseResume($settings->isAllowPauseAndResume());
        $page->setAllowSkip($settings->isAllowSkip());
        $page->setAllowReorder($settings->isAllowReorder());
        $page->setAllowUpdatePayment($settings->isAllowUpdatePayment());

        return $page;
    }

    private function buildCountryCriteria(): Criteria
    {
        return (new Criteria())
            ->addAssociation('states')
            ->addSorting(new FieldSorting('position'))
            ->addSorting(new FieldSorting('name'))
            ->addSorting(new FieldSorting('states.position'))
            ->addSorting(new FieldSorting('states.name'))
        ;
    }
}
