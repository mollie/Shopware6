<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Controller;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Shopware\Component\Subscription\Controller\RescueApiController;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

#[CoversClass(RescueApiController::class)]
final class RescueApiControllerTest extends TestCase
{
    public function testListReturnsErrorWhenCustomerIsMissing(): void
    {
        $controller = new RescueApiController(
            $this->createMock(MollieApiFactory::class),
            $this->buildCustomerRepository(null),
            new FakeSubscriptionRepository(),
            new FakeSettingsService(),
            new NullLogger()
        );

        $response = $controller->listUserMollieSubscriptions('missing-customer', Context::createDefaultContext());

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame(['Customer with ID missing-customer not found in Shopware'], $body['errors']);
    }

    public function testListReturnsEmptySubscriptionsWhenCustomerHasNoMollieIds(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('cust-1');
        $customer->setSalesChannelId('sc-1');
        $customer->setCustomFields([]);

        $controller = new RescueApiController(
            $this->createMock(MollieApiFactory::class),
            $this->buildCustomerRepository($customer),
            new FakeSubscriptionRepository(),
            new FakeSettingsService(),
            new NullLogger()
        );

        $response = $controller->listUserMollieSubscriptions('cust-1', Context::createDefaultContext());

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertTrue($body['success']);
        $this->assertSame([], $body['subscriptions']);
    }

    /**
     * @return EntityRepository<CustomerCollection<CustomerEntity>>
     */
    private function buildCustomerRepository(?CustomerEntity $customer): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use ($customer): EntitySearchResult {
                $collection = new CustomerCollection();
                if ($customer !== null) {
                    $collection->add($customer);
                }

                return new EntitySearchResult(CustomerEntity::class, $collection->count(), $collection, null, $criteria, $context);
            }
        );

        return $repository;
    }
}
