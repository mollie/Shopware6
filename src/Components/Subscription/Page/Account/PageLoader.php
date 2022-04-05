<?php

namespace Kiener\MolliePayments\Components\Subscription\Page\Account;


use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\CustomerService;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;


class PageLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mollieSubscriptionsRepository;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @param EntityRepositoryInterface $mollieSubscriptionsRepository
     * @param GenericPageLoaderInterface $genericLoader
     * @param CustomerService $customerService
     */
    public function __construct(EntityRepositoryInterface $mollieSubscriptionsRepository, GenericPageLoaderInterface $genericLoader, CustomerService $customerService)
    {
        $this->mollieSubscriptionsRepository = $mollieSubscriptionsRepository;
        $this->genericLoader = $genericLoader;
        $this->customerService = $customerService;
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

        $page->setSubscriptions($storefrontSubscriptions);
        $page->setDeepLinkCode($request->get('deepLinkCode'));
        $page->setTotal($subscriptions->getTotal());

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
        return $this->mollieSubscriptionsRepository->search($criteria, $context->getContext());
    }

    /**
     * @param Request $request
     * @param string|null $customerId
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

}
