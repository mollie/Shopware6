<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['store-api']])]
final class ListSubscriptionsRoute extends AbstractListSubscriptionsRoute
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository
    ) {
    }

    public function getDecorated(): AbstractListSubscriptionsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/mollie/subscription',
        name: 'store-api.mollie.subscription',
        defaults: ['_loginRequired' => true],
        methods: ['GET', 'POST']
    )]
    public function list(Request $request, SalesChannelContext $context): SubscriptionsListResponse
    {
        $customer = $context->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw new UnauthorizedHttpException('No customer is signed in');
        }

        $limit = max(1, (int) $request->get('limit', 10));
        $page = max(1, (int) $request->get('p', 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addAssociation('historyEntries');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('order.lineItems');

        /** @var EntitySearchResult<SubscriptionCollection<SubscriptionEntity>> $result */
        $result = $this->subscriptionRepository->search($criteria, $context->getContext());

        return new SubscriptionsListResponse($result);
    }
}
