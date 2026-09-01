<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\ListSubscriptionsRoute;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[CoversClass(ListSubscriptionsRoute::class)]
final class ListSubscriptionsRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private FakeSubscriptionRepository $subscriptionRepository;

    private ListSubscriptionsRoute $route;

    protected function setUp(): void
    {
        $this->subscriptionRepository = new FakeSubscriptionRepository();
        $this->route = new ListSubscriptionsRoute($this->subscriptionRepository);
    }

    public function testTheRouteCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->route->getDecorated();
    }

    public function testTheListIsRefusedWithoutASignedInCustomer(): void
    {
        $this->expectException(UnauthorizedHttpException::class);

        $this->route->list(new Request(), new FakeSalesChannelContext());
    }

    public function testTheCustomerSeesTheirOwnSubscriptions(): void
    {
        $this->subscriptionRepository->add($this->subscription('subscription-id'));

        $response = $this->route->list(new Request(), $this->authenticatedContext());

        $subscriptions = $response->getObject()->all()['subscriptions'];
        self::assertCount(1, $subscriptions);
        self::assertSame('subscription-id', array_values($subscriptions)[0]->getId());
    }

    public function testOnlyTheSubscriptionsOfTheSignedInCustomerAreLookedUp(): void
    {
        $this->route->list(new Request(), $this->authenticatedContext());

        $filters = $this->criteria()->getFilters();
        self::assertCount(1, $filters);
        self::assertInstanceOf(EqualsFilter::class, $filters[0]);
        self::assertSame('customerId', $filters[0]->getField());
        self::assertSame(self::CUSTOMER_ID, $filters[0]->getValue());
    }

    public function testTheNewestSubscriptionIsListedFirst(): void
    {
        $this->route->list(new Request(), $this->authenticatedContext());

        $sorting = $this->criteria()->getSorting();
        self::assertSame('createdAt', $sorting[0]->getField());
        self::assertSame(FieldSorting::DESCENDING, $sorting[0]->getDirection());
    }

    public function testTenSubscriptionsPerPageAreListedByDefault(): void
    {
        $this->route->list(new Request(), $this->authenticatedContext());

        self::assertSame(10, $this->criteria()->getLimit());
        self::assertSame(0, $this->criteria()->getOffset());
    }

    public function testTheRequestedPageIsTranslatedIntoAnOffset(): void
    {
        $this->route->list(new Request(['limit' => 5, 'p' => 3]), $this->authenticatedContext());

        self::assertSame(5, $this->criteria()->getLimit());
        self::assertSame(10, $this->criteria()->getOffset());
    }

    public function testANonsensicalPageOrLimitFallsBackToTheFirstPage(): void
    {
        $this->route->list(new Request(['limit' => 0, 'p' => -2]), $this->authenticatedContext());

        self::assertSame(1, $this->criteria()->getLimit());
        self::assertSame(0, $this->criteria()->getOffset());
    }

    public function testTheTotalIsCountedSoTheAccountPageCanPaginate(): void
    {
        $this->route->list(new Request(), $this->authenticatedContext());

        self::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $this->criteria()->getTotalCountMode());
    }

    public function testTheAssociationsTheAccountPageRendersAreLoaded(): void
    {
        $this->route->list(new Request(), $this->authenticatedContext());

        $associations = array_keys($this->criteria()->getAssociations());

        self::assertContains('historyEntries', $associations);
        self::assertContains('currency', $associations);
        self::assertContains('order', $associations);
    }

    private function criteria(): Criteria
    {
        $criteria = $this->subscriptionRepository->getSearchCriteria();
        self::assertCount(1, $criteria);

        return $criteria[0];
    }

    private function subscription(string $id): SubscriptionEntity
    {
        return SubscriptionEntityBuilder::create()
            ->withId($id)
            ->withCustomerId(self::CUSTOMER_ID)
            ->build()
        ;
    }

    private function authenticatedContext(): FakeSalesChannelContext
    {
        $customer = new CustomerEntity();
        $customer->setId(self::CUSTOMER_ID);

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        return $context;
    }
}
