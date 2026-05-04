<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Route\UpdateAddressException;
use Mollie\Shopware\Component\Subscription\Route\UpdateAddressRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionAddressBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionDataService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(UpdateAddressRoute::class)]
final class UpdateAddressRouteTest extends TestCase
{
    public function testUpdateBillingThrowsWhenNoCustomerIsSignedIn(): void
    {
        $route = $this->buildRoute();
        $context = new FakeSalesChannelContext();

        $this->expectException(UpdateAddressException::class);
        $this->expectExceptionMessage('No customer is signed in');

        $route->updateBilling('subscription-id', $this->buildValidRequestData(), $context);
    }

    public function testUpdateBillingThrowsWhenSubscriptionsAreDisabled(): void
    {
        $settings = new FakeSettingsService(
            subscriptionSettings: new SubscriptionSettings(enabled: false, allowEditAddress: true)
        );
        $route = $this->buildRoute(settings: $settings);

        $this->expectException(UpdateAddressException::class);
        $this->expectExceptionMessage('subscriptions are disabled');

        $route->updateBilling('subscription-id', $this->buildValidRequestData(), $this->buildAuthenticatedContext());
    }

    public function testUpdateBillingThrowsWhenAddressEditingIsDisabled(): void
    {
        $settings = new FakeSettingsService(
            subscriptionSettings: new SubscriptionSettings(enabled: true, allowEditAddress: false)
        );
        $route = $this->buildRoute(settings: $settings);

        $this->expectException(UpdateAddressException::class);
        $this->expectExceptionMessage('Address editing is disabled');

        $route->updateBilling('subscription-id', $this->buildValidRequestData(), $this->buildAuthenticatedContext());
    }

    public function testUpdateBillingThrowsWhenSubscriptionBelongsToAnotherCustomer(): void
    {
        $dataService = new FakeSubscriptionDataService($this->buildSubscriptionData(customerId: 'someone-else'));
        $route = $this->buildRoute(dataService: $dataService);

        $this->expectException(UpdateAddressException::class);
        $this->expectExceptionMessage('does not belong to the current customer');

        $route->updateBilling('subscription-id', $this->buildValidRequestData(), $this->buildAuthenticatedContext());
    }

    public function testUpdateBillingThrowsWhenRequiredFieldIsMissing(): void
    {
        $route = $this->buildRoute();

        $this->expectException(UpdateAddressException::class);
        $this->expectExceptionMessage('Required address field');

        $route->updateBilling('subscription-id', new RequestDataBag([]), $this->buildAuthenticatedContext());
    }

    public function testUpdateBillingLowercasesSubscriptionIdBeforeLoadingData(): void
    {
        $dataService = new FakeSubscriptionDataService($this->buildSubscriptionData());
        $route = $this->buildRoute(dataService: $dataService);

        $route->updateBilling('SUBSCRIPTION-ID', $this->buildValidRequestData(), $this->buildAuthenticatedContext());

        $this->assertSame('subscription-id', $dataService->getCalls()[0]['subscriptionId']);
    }

    public function testUpdateBillingPersistsExistingAddressIdAndWritesHistoryEntry(): void
    {
        $existingBilling = SubscriptionAddressBuilder::create()->withId('existing-billing-id')->build();
        $existingShipping = SubscriptionAddressBuilder::create()->withId('existing-shipping-id')->build();
        $dataService = new FakeSubscriptionDataService($this->buildSubscriptionData(
            billingAddress: $existingBilling,
            shippingAddress: $existingShipping,
        ));
        $repository = new FakeSubscriptionRepository();

        $route = $this->buildRoute(dataService: $dataService, subscriptionRepository: $repository);

        $response = $route->updateBilling('subscription-id', $this->buildValidRequestData(), $this->buildAuthenticatedContext());

        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame('subscription-id', $payload['id']);
        $this->assertArrayHasKey('billingAddress', $payload);
        $this->assertArrayNotHasKey('shippingAddress', $payload);
        $this->assertSame('existing-billing-id', $payload['billingAddress']['id']);
        $this->assertSame('subscription-id', $payload['billingAddress']['subscriptionId']);
        $this->assertSame('Jane', $payload['billingAddress']['firstName']);
        $this->assertSame('country-uk', $payload['billingAddress']['countryId']);
        $this->assertSame('state-london', $payload['billingAddress']['countryStateId']);
        $this->assertSame('billing address updated', $payload['historyEntries'][0]['comment']);
        $this->assertSame('sub_test123', $payload['historyEntries'][0]['mollieId']);
        $this->assertSame('existing-billing-id', $response->getObject()->get('addressId'));
        $this->assertSame('billing', $response->getObject()->get('type'));
    }

    public function testUpdateBillingGeneratesNewAddressIdWhenSubscriptionHasNoBillingAddress(): void
    {
        $shipping = SubscriptionAddressBuilder::create()->withId('keep-shipping')->build();
        $subscription = SubscriptionEntityBuilder::create()
            ->withCustomerId('customer-id')
            ->withoutBillingAddress()
            ->withShippingAddress($shipping)
            ->build();
        $order = new OrderEntity();
        $order->setId('order-id');
        $struct = new SubscriptionDataStruct(
            $subscription,
            $order,
            CustomerBuilder::create()->build(),
            SubscriptionAddressBuilder::create()->build(),
            $shipping
        );

        $repository = new FakeSubscriptionRepository();
        $route = $this->buildRoute(
            dataService: new FakeSubscriptionDataService($struct),
            subscriptionRepository: $repository,
        );

        $response = $route->updateBilling('subscription-id', $this->buildValidRequestData(), $this->buildAuthenticatedContext());

        $writtenAddressId = $repository->getLastUpsert()['billingAddress']['id'];
        $this->assertNotSame('', $writtenAddressId);
        $this->assertNotSame('keep-shipping', $writtenAddressId);
        $this->assertSame($writtenAddressId, $response->getObject()->get('addressId'));
    }

    public function testUpdateShippingTargetsShippingAddressAndUsesShippingHistoryComment(): void
    {
        $existingBilling = SubscriptionAddressBuilder::create()->withId('existing-billing-id')->build();
        $existingShipping = SubscriptionAddressBuilder::create()->withId('existing-shipping-id')->build();
        $dataService = new FakeSubscriptionDataService($this->buildSubscriptionData(
            billingAddress: $existingBilling,
            shippingAddress: $existingShipping,
        ));
        $repository = new FakeSubscriptionRepository();

        $route = $this->buildRoute(dataService: $dataService, subscriptionRepository: $repository);

        $route->updateShipping('subscription-id', $this->buildValidRequestData(), $this->buildAuthenticatedContext());

        $payload = $repository->getLastUpsert();
        $this->assertArrayHasKey('shippingAddress', $payload);
        $this->assertArrayNotHasKey('billingAddress', $payload);
        $this->assertSame('existing-shipping-id', $payload['shippingAddress']['id']);
        $this->assertSame('shipping address updated', $payload['historyEntries'][0]['comment']);
    }

    public function testRequestDataLowercasesSalutationAndCountryStateIds(): void
    {
        $existingBilling = SubscriptionAddressBuilder::create()->withId('existing-billing-id')->build();
        $existingShipping = SubscriptionAddressBuilder::create()->withId('existing-shipping-id')->build();
        $dataService = new FakeSubscriptionDataService($this->buildSubscriptionData(
            billingAddress: $existingBilling,
            shippingAddress: $existingShipping,
        ));
        $repository = new FakeSubscriptionRepository();
        $route = $this->buildRoute(dataService: $dataService, subscriptionRepository: $repository);

        $data = new RequestDataBag([
            'salutationId' => 'SALUTATION-ID',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'street' => 'Main 1',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'countryId' => 'COUNTRY-UK',
            'countryStateId' => 'STATE-LONDON',
        ]);

        $route->updateBilling('subscription-id', $data, $this->buildAuthenticatedContext());

        $written = $repository->getLastUpsert()['billingAddress'];
        $this->assertSame('salutation-id', $written['salutationId']);
        $this->assertSame('country-uk', $written['countryId']);
        $this->assertSame('state-london', $written['countryStateId']);
    }

    private function buildRoute(
        ?FakeSettingsService $settings = null,
        ?FakeSubscriptionDataService $dataService = null,
        ?FakeSubscriptionRepository $subscriptionRepository = null,
    ): UpdateAddressRoute {
        $settings ??= new FakeSettingsService(
            subscriptionSettings: new SubscriptionSettings(enabled: true, allowEditAddress: true)
        );
        $dataService ??= new FakeSubscriptionDataService($this->buildSubscriptionData());
        $subscriptionRepository ??= new FakeSubscriptionRepository();

        return new UpdateAddressRoute(
            $settings,
            $dataService,
            $subscriptionRepository,
            new NullLogger()
        );
    }

    private function buildSubscriptionData(
        string $customerId = 'customer-id',
        ?\Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity $billingAddress = null,
        ?\Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity $shippingAddress = null,
    ): SubscriptionDataStruct {
        $billingAddress ??= SubscriptionAddressBuilder::create()->build();
        $shippingAddress ??= SubscriptionAddressBuilder::create()->build();

        $subscription = SubscriptionEntityBuilder::create()
            ->withCustomerId($customerId)
            ->withBillingAddress($billingAddress)
            ->withShippingAddress($shippingAddress)
            ->build();

        $order = new OrderEntity();
        $order->setId('order-id');

        return new SubscriptionDataStruct(
            $subscription,
            $order,
            CustomerBuilder::create()->build(),
            $billingAddress,
            $shippingAddress
        );
    }

    private function buildAuthenticatedContext(string $customerId = 'customer-id'): FakeSalesChannelContext
    {
        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        return $context;
    }

    private function buildValidRequestData(): RequestDataBag
    {
        return new RequestDataBag([
            'salutationId' => 'salutation-id',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'street' => 'Main 1',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'countryId' => 'country-uk',
            'countryStateId' => 'state-london',
        ]);
    }
}