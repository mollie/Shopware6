<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\SubscriptionsListResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

#[CoversClass(SubscriptionsListResponse::class)]
final class SubscriptionsListResponseTest extends TestCase
{
    public function testStoreApiPayloadListsEverySubscriptionOfTheCustomer(): void
    {
        $response = new SubscriptionsListResponse($this->searchResult(['sub-1', 'sub-2']));

        $subscriptions = $response->getObject()->all()['subscriptions'];

        $this->assertCount(2, $subscriptions);
        $this->assertSame(['sub-1', 'sub-2'], array_map(static fn (SubscriptionEntity $s): string => $s->getId(), $subscriptions));
    }

    public function testCustomerWithoutSubscriptionsGetsAnEmptyList(): void
    {
        $response = new SubscriptionsListResponse($this->searchResult([]));

        $this->assertSame(['subscriptions' => []], $response->getObject()->all());
    }

    public function testApiAliasIdentifiesTheSubscriptionsList(): void
    {
        $response = new SubscriptionsListResponse($this->searchResult([]));

        $this->assertSame('mollie_payments_subscriptions_list', $response->getObject()->getApiAlias());
    }

    public function testTheOriginalSearchResultStaysAvailableForPagination(): void
    {
        $searchResult = $this->searchResult(['sub-1']);

        $response = new SubscriptionsListResponse($searchResult);

        $this->assertSame($searchResult, $response->getEntitySearchResult());
    }

    /**
     * @param list<string> $subscriptionIds
     *
     * @return EntitySearchResult<SubscriptionCollection<SubscriptionEntity>>
     */
    private function searchResult(array $subscriptionIds): EntitySearchResult
    {
        $entities = new SubscriptionCollection();
        foreach ($subscriptionIds as $subscriptionId) {
            $subscription = new SubscriptionEntity();
            $subscription->setId($subscriptionId);
            $entities->add($subscription);
        }

        /** @var EntitySearchResult<SubscriptionCollection<SubscriptionEntity>> $searchResult */
        return new EntitySearchResult(
            'mollie_subscription',
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
