<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ExpressMethod\AccountService;
use Mollie\Shopware\Unit\Fake\FakeAddressSynchronizer;
use Mollie\Shopware\Unit\Fake\FakeContextSwitchRoute;
use Mollie\Shopware\Unit\Fake\FakeCountryRepository;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeRegisterRoute;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContextService;
use Mollie\Shopware\Unit\Fake\FakeSalutationRepository;
use Mollie\Shopware\Unit\Fake\FakeShopwareAccountService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationEntity;

#[CoversClass(AccountService::class)]
final class AccountServiceTest extends TestCase
{
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    /**
     * Already logged-in customer → syncAddresses called with that customer.
     */
    public function testLoggedInCustomerAddressesSynced(): void
    {
        $customer = $this->makeCustomer('customer-logged-in');
        $syncSpy = new FakeAddressSynchronizer();
        $loginTracker = new FakeShopwareAccountService();
        $newContext = $this->createMock(SalesChannelContext::class);

        $service = $this->buildService(
            customersFoundByEmail: [],
            syncSpy: $syncSpy,
            loginTracker: $loginTracker,
            newContext: $newContext,
        );

        $result = $service->loginOrCreateAccount(
            'pm-id',
            $this->makeAddress(),
            $this->makeAddress(),
            $this->makeSalesChannelContext($customer),
        );

        $this->assertSame('customer-logged-in', $syncSpy->getLastSyncedCustomerId());
        $this->assertNull($loginTracker->getLoggedInId(), 'loginById must not be called when customer is already in context');
        $this->assertSame($newContext, $result);
    }

    /**
     * Guest found by email → loginById called, then syncAddresses with that guest.
     */
    public function testReturningGuestGetsLoggedInAndSynced(): void
    {
        $guest = $this->makeCustomer('customer-returning', guest: true);
        $syncSpy = new FakeAddressSynchronizer();
        $loginTracker = new FakeShopwareAccountService();

        $service = $this->buildService(
            customersFoundByEmail: [$guest],
            syncSpy: $syncSpy,
            loginTracker: $loginTracker,
        );

        $service->loginOrCreateAccount(
            'pm-id',
            $this->makeAddress(),
            $this->makeAddress(),
            $this->makeSalesChannelContext(null),
        );

        $this->assertSame('customer-returning', $loginTracker->getLoggedInId(), 'loginById must be called with the found guest ID');
        $this->assertSame('customer-returning', $syncSpy->getLastSyncedCustomerId());
    }

    /**
     * No existing guest by email → RegisterRoute called, new customer synced.
     */
    public function testNewGuestIsRegisteredAndSynced(): void
    {
        $newGuest = $this->makeCustomer('customer-new', guest: true);
        $syncSpy = new FakeAddressSynchronizer();
        $loginTracker = new FakeShopwareAccountService();
        $registerRoute = new FakeRegisterRoute($newGuest);

        $service = $this->buildService(
            customersFoundByEmail: [],
            syncSpy: $syncSpy,
            loginTracker: $loginTracker,
            registerRoute: $registerRoute,
        );

        $service->loginOrCreateAccount(
            'pm-id',
            $this->makeAddress(),
            $this->makeAddress(),
            $this->makeSalesChannelContext(null),
        );

        $this->assertTrue($registerRoute->wasRegistrationCalled(), 'RegisterRoute::register must be called for unknown guests');
        $this->assertNull($loginTracker->getLoggedInId(), 'loginById must not be called when creating a new guest');
        $this->assertSame('customer-new', $syncSpy->getLastSyncedCustomerId());
    }

    /**
     * Country map is always built once upfront and forwarded to syncAddresses —
     * regardless of whether the customer is new, returning, or already logged in.
     */
    public function testCountryMapIsAlwaysForwardedToSynchronizer(): void
    {
        $customer = $this->makeCustomer('customer-logged-in');
        $syncSpy = new FakeAddressSynchronizer();

        $service = $this->buildService(
            customersFoundByEmail: [],
            syncSpy: $syncSpy,
        );

        $service->loginOrCreateAccount(
            'pm-id',
            $this->makeAddress(),
            $this->makeAddress(),
            $this->makeSalesChannelContext($customer),
        );

        $this->assertNotEmpty($syncSpy->getLastCountryMap(), 'country map must always be pre-built and forwarded to syncAddresses');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCustomer(string $id, bool $guest = false): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setActive(true);
        $customer->setGuest($guest);
        $customer->setCustomerNumber($id);
        $customer->setDefaultBillingAddressId($id . '-billing-address');

        return $customer;
    }

    private function makeAddress(string $email = 'test@example.com'): Address
    {
        return new Address($email, '', 'John', 'Doe', 'Main St 1', '10115', 'Berlin', 'DE');
    }

    private function makeSalesChannelContext(?CustomerEntity $customer): SalesChannelContext
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment-method-id');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getContext')->willReturn($this->context);
        $context->method('getDomainId')->willReturn(null);

        return $context;
    }

    private function buildService(
        array $customersFoundByEmail,
        FakeAddressSynchronizer $syncSpy,
        ?FakeShopwareAccountService $loginTracker = null,
        ?FakeRegisterRoute $registerRoute = null,
        ?SalesChannelContext $newContext = null,
    ): AccountService {
        $newContext ??= $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('country-de-uuid');
        $country->setIso('DE');

        $salutation = new SalutationEntity();
        $salutation->setId('salutation-uuid');
        $salutation->setSalutationKey('not_specified');

        return new AccountService(
            customerRepository: new FakeCustomerRepository(new CustomerCollection($customersFoundByEmail)),
            countryRepository: new FakeCountryRepository([$country]),
            salutationRepository: new FakeSalutationRepository([$salutation]),
            registerRoute: $registerRoute ?? new FakeRegisterRoute($this->makeCustomer('fallback')),
            accountService: $loginTracker ?? new FakeShopwareAccountService(),
            contextSwitchRoute: new FakeContextSwitchRoute(),
            salesChannelContextService: new FakeSalesChannelContextService($newContext),
            addressSynchronizer: $syncSpy,
            logger: new NullLogger(),
        );
    }
}
