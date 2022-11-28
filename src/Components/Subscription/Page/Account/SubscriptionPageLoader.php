<?php

namespace Kiener\MolliePayments\Components\Subscription\Page\Account;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionPageLoader
{

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EntityRepositoryInterface
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
     * @param GenericPageLoaderInterface $genericLoader
     * @param EntityRepositoryInterface $repoSubscriptions
     * @param CustomerService $customerService
     * @param AbstractCountryRoute $countryRoute
     * @param AbstractSalutationRoute $salutationRoute
     * @param SettingsService $settingsService
     */
    public function __construct(GenericPageLoaderInterface $genericLoader, EntityRepositoryInterface $repoSubscriptions, CustomerService $customerService, AbstractCountryRoute $countryRoute, AbstractSalutationRoute $salutationRoute, SettingsService $settingsService)
    {
        $this->genericLoader = $genericLoader;
        $this->repoSubscriptions = $repoSubscriptions;
        $this->customerService = $customerService;
        $this->countryRoute = $countryRoute;
        $this->salutationRoute = $salutationRoute;
        $this->settingsService = $settingsService;
    }


    /**
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     * @return SubscriptionPage
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SubscriptionPage
    {
        if (!$salesChannelContext->getCustomer() && $request->get('deepLinkCode', false) === false) {
            throw new CustomerNotLoggedInException();
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

        /** @var StorefrontSearchResult<SubscriptionEntity> $storefrontSubscriptions */
        $storefrontSubscriptions = StorefrontSearchResult::createFrom($subscriptions);

        # ---------------------------------------------------------------------------------------------
        # assign data for our page

        $page->setSubscriptions($storefrontSubscriptions);
        $page->setDeepLinkCode($request->get('deepLinkCode'));
        $page->setTotal($subscriptions->getTotal());

        $page->setSalutations($this->getSalutations($salesChannelContext));
        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setAllowAddressEditing($settings->isSubscriptionsAllowAddressEditing());
        $page->setAllowPauseResume($settings->isSubscriptionsAllowPauseResume());
        $page->setAllowSkip($settings->isSubscriptionsAllowSkip());

        # ---------------------------------------------------------------------------------------------

        return $page;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @return null|StorefrontSearchResult<SubscriptionEntity>
     */
    private function getSubscriptions(Request $request, SalesChannelContext $context): ?EntitySearchResult
    {
        $customer = $context->getCustomer();

        if (!$customer instanceof CustomerEntity) {
            return null;
        }

        $customerId = $this->customerService->getMollieCustomerId(
            $customer->getId(),
            $context->getSalesChannelId(),
            $context->getContext()
        );
        $criteria = $this->createCriteria($request, $customerId);

        /** @var StorefrontSearchResult<SubscriptionEntity> */
        return $this->repoSubscriptions->search($criteria, $context->getContext());
    }

    /**
     * @param Request $request
     * @param null|string $customerId
     * @return Criteria
     */
    private function createCriteria(Request $request, string $customerId = null): Criteria
    {
        $limit = $request->get('limit');
        $limit = $limit ? (int)$limit : 10;
        $page = $request->get('p');
        $page = $page ? (int)$page : 1;

        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit($limit)
            ->setOffset(($page - 1) * $limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if (!is_null($customerId)) {
            $criteria->addFilter(new EqualsFilter('mollieCustomerId', $customerId));
        }

        if ($request->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->get('deepLinkCode')));
        }

        return $criteria;
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     * @return CountryCollection
     */
    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('country.active', true))
            ->addAssociation('states');

        $countries = $this->countryRoute->load(new Request(), $criteria, $salesChannelContext)->getCountries();

        $countries->sortCountryAndStates();

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
