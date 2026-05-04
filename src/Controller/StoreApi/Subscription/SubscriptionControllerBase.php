<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionsListResponse;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SubscriptionControllerBase
{
    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    public function __construct(SubscriptionRepository $repoSubscriptions)
    {
        $this->repoSubscriptions = $repoSubscriptions;
    }

    /**
     * @throws \Throwable
     *
     * @return SubscriptionsListResponse
     */
    public function getSubscriptions(SalesChannelContext $context): StoreApiResponse
    {
        $customer = $context->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw new UnauthorizedHttpException('Unauthorized request! No customer is signed in!');
        }

        $result = $this->repoSubscriptions->findByCustomer(
            $customer->getId(),
            false,
            $context->getContext()
        );

        /** @var SubscriptionEntity[] $subscriptions */
        $subscriptions = $result->getElements();

        $collection = new SubscriptionCollection($subscriptions);
        $flatList = $collection->getFlatList();

        return new SubscriptionsListResponse($flatList);
    }
}
