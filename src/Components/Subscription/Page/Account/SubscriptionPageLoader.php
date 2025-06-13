<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Page\Account;

use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EntityRepository<SubscriptionCollection<SubscriptionEntity>>
     */
    private $repoSubscriptions;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var AbstractCountryRoute
     */
    private $countryRoute;

    /**
     * @var AbstractSalutationRoute
     */
    private $salutationRoute;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $repoSubscriptions
     */
    public function __construct(GenericPageLoaderInterface $genericLoader, $repoSubscriptions, CustomerService $customerService, AbstractCountryRoute $countryRoute, AbstractSalutationRoute $salutationRoute, SettingsService $settingsService, ContainerInterface $container)
    {
        $this->genericLoader = $genericLoader;
        $this->repoSubscriptions = $repoSubscriptions;
        $this->customerService = $customerService;
        $this->countryRoute = $countryRoute;
        $this->salutationRoute = $salutationRoute;
        $this->settingsService = $settingsService;
        $this->container = $container;
    }

    /**
     * @throws \Exception
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SubscriptionPage
    {
        if (! $salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw new \Exception('Customer not logged in');
        }

        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannelId());

        /** @var SubscriptionPage $page */
        $page = $this->genericLoader->load($request, $salesChannelContext);

        /** @var SubscriptionPage $page */
        $page = SubscriptionPage::createFrom($page);

        if ($page->getMetaInformation() instanceof MetaInformation) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $subscriptions = $this->getSubscriptions($request, $salesChannelContext);

        if (is_null($subscriptions)) {
            return $page;
        }

        /** @var EntitySearchResult<SubscriptionCollection<SubscriptionEntity>> $storefrontSubscriptions */
        $storefrontSubscriptions = EntitySearchResult::createFrom($subscriptions);

        // ---------------------------------------------------------------------------------------------
        // assign data for our page

        $page->setSubscriptions($storefrontSubscriptions);
        $page->setDeepLinkCode($request->get('deepLinkCode'));
        $page->setTotal($subscriptions->getTotal());

        $page->setSalutations($this->getSalutations($salesChannelContext));
        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setAllowAddressEditing($settings->isSubscriptionsAllowAddressEditing());
        $page->setAllowPauseResume($settings->isSubscriptionsAllowPauseResume());
        $page->setAllowSkip($settings->isSubscriptionsAllowSkip());

        // ---------------------------------------------------------------------------------------------

        return $page;
    }

    /**
     * @return null|EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    private function getSubscriptions(Request $request, SalesChannelContext $context): ?EntitySearchResult
    {
        $customer = $context->getCustomer();

        if (! $customer instanceof CustomerEntity) {
            return null;
        }

        $customerId = $this->customerService->getMollieCustomerId(
            $customer->getId(),
            $context->getSalesChannelId(),
            $context->getContext()
        );
        $criteria = $this->createCriteria($request, $customerId);

        return $this->repoSubscriptions->search($criteria, $context->getContext());
    }

    private function createCriteria(Request $request, ?string $customerId = null): Criteria
    {
        $limit = $request->get('limit');
        $limit = $limit ? (int) $limit : 10;
        $page = $request->get('p');
        $page = $page ? (int) $page : 1;

        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit($limit)
            ->setOffset(($page - 1) * $limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
        ;

        if (! is_null($customerId)) {
            $criteria->addFilter(new EqualsFilter('mollieCustomerId', $customerId));
        }

        if ($request->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')));
        }

        return $criteria;
    }

    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('states')
            ->addSorting(new FieldSorting('position', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::DESCENDING, true))
            ->addSorting(new FieldSorting('states.position', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('states.name', FieldSorting::DESCENDING, true))
        ;

        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);

        if ($versionCompare->gt('6.3.5.2')) {
            $countries = $this->countryRoute->load(new Request(), $criteria, $salesChannelContext)->getCountries();
        } else {
            // @phpstan-ignore-next-line
            $countries = $this->countryRoute->load($criteria, $salesChannelContext)->getCountries();
        }

        return $countries;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext, new Criteria())->getSalutations();

        $salutations->sort(function (SalutationEntity $a, SalutationEntity $b) {
            return $b->getSalutationKey() <=> $a->getSalutationKey();
        });

        return $salutations;
    }
}
