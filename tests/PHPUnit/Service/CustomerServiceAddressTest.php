<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Customer;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use MolliePayments\Tests\Fakes\FakeTranslator;
use MolliePayments\Tests\Fakes\Repositories\FakeCustomerRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @covers \Kiener\MolliePayments\Service\CustomerService::createGuestAccount
 * @covers \Kiener\MolliePayments\Service\CustomerService::reuseOrCreateAddresses
 */
class CustomerServiceAddressTest extends TestCase
{
    /**
     * When existing customer addresses are found for both shipping and billing Mollie IDs,
     * the upserted customer data must map each ID to the correct Shopware key.
     */
    public function testReuseOrCreateAddressesSetsCorrectDefaultShippingAndBillingIds(): void
    {
        // Arrange: two distinct addresses with different streets → different Mollie IDs
        $shipping = $this->buildAddress('Shipping Street 1', 'Berlin', '10115');
        $billing = $this->buildAddress('Billing Avenue 5', 'Munich', '80331');

        $shippingEntityId = 'entity-shipping';
        $billingEntityId = 'entity-billing';

        $shippingEntity = $this->createMock(CustomerAddressEntity::class);
        $shippingEntity->method('getId')->willReturn($shippingEntityId);
        $shippingEntity->method('getCustomFields')->willReturn([
            CustomFieldsInterface::MOLLIE_KEY => [
                CustomerService::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID => $shipping->getMollieAddressId(),
            ],
        ]);

        $billingEntity = $this->createMock(CustomerAddressEntity::class);
        $billingEntity->method('getId')->willReturn($billingEntityId);
        $billingEntity->method('getCustomFields')->willReturn([
            CustomFieldsInterface::MOLLIE_KEY => [
                CustomerService::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID => $billing->getMollieAddressId(),
            ],
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getTotal')->willReturn(2);
        $searchResult->method('getElements')->willReturn([$shippingEntity, $billingEntity]);

        $customerAddressRepo = $this->createMock(EntityRepository::class);
        $customerAddressRepo->method('search')->willReturn($searchResult);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getId')->willReturn('customer-1');
        $customer->method('getSalutationId')->willReturn(null);

        $fakeCustomerRepo = new FakeCustomerRepository(new CustomerDefinition());
        $fakeCustomerRepo->entityWrittenContainerEvents = [$this->createMock(EntityWrittenContainerEvent::class)];

        $service = $this->buildCustomerService(
            $fakeCustomerRepo,
            $customerAddressRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ContainerInterface::class),
            $this->createMock(SettingsService::class),
        );

        // Act
        $service->reuseOrCreateAddresses($customer, $shipping, $this->createMock(Context::class), $billing);

        // Assert: shipping ID → defaultShippingAddressId, billing ID → defaultBillingAddressId
        $savedData = array_pop($fakeCustomerRepo->data)[0];

        $this->assertSame(
            $shippingEntityId,
            $savedData['defaultShippingAddressId'],
            'defaultShippingAddressId must point to the shipping address entity, not billing'
        );
        $this->assertSame(
            $billingEntityId,
            $savedData['defaultBillingAddressId'],
            'defaultBillingAddressId must point to the billing address entity'
        );
    }

    /**
     * When only a shipping address is found (no billing address provided), the shipping
     * entity ID must be used for both default addresses — not a random billing ID.
     */
    public function testReuseOrCreateAddressesUsesSameIdForBothWhenNoBillingAddress(): void
    {
        $shipping = $this->buildAddress('Shipping Street 1', 'Berlin', '10115');

        $shippingEntityId = 'entity-shipping-only';

        $shippingEntity = $this->createMock(CustomerAddressEntity::class);
        $shippingEntity->method('getId')->willReturn($shippingEntityId);
        $shippingEntity->method('getCustomFields')->willReturn([
            CustomFieldsInterface::MOLLIE_KEY => [
                CustomerService::CUSTOM_FIELDS_KEY_EXPRESS_ADDRESS_ID => $shipping->getMollieAddressId(),
            ],
        ]);

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getTotal')->willReturn(1);
        $searchResult->method('getElements')->willReturn([$shippingEntity]);

        $customerAddressRepo = $this->createMock(EntityRepository::class);
        $customerAddressRepo->method('search')->willReturn($searchResult);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getId')->willReturn('customer-2');
        $customer->method('getSalutationId')->willReturn(null);

        $fakeCustomerRepo = new FakeCustomerRepository(new CustomerDefinition());
        $fakeCustomerRepo->entityWrittenContainerEvents = [$this->createMock(EntityWrittenContainerEvent::class)];

        $service = $this->buildCustomerService(
            $fakeCustomerRepo,
            $customerAddressRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ContainerInterface::class),
            $this->createMock(SettingsService::class),
        );

        // no billing address passed
        $service->reuseOrCreateAddresses($customer, $shipping, $this->createMock(Context::class));

        $savedData = array_pop($fakeCustomerRepo->data)[0];
        $this->assertSame($shippingEntityId, $savedData['defaultShippingAddressId']);
        // billing falls back to shipping when no separate billing address exists
        $this->assertSame($shippingEntityId, $savedData['defaultBillingAddressId']);
    }

    /**
     * When createGuestAccount is called with a distinct billing address, the 'billingAddress'
     * key in the data bag passed to RegisterRoute must contain the billing address data —
     * not a copy of the shipping address.
     */
    public function testCreateGuestAccountSetsBillingAddressDataFromBillingNotShipping(): void
    {
        $shippingAddress = $this->buildAddress('Shipping Road 1', 'Berlin', '10115');
        $billingAddress = $this->buildAddress('Billing Lane 99', 'Hamburg', '20095');

        // Country / salutation lookups must succeed
        $countryIdResult = $this->buildIdSearchResult(['country-id']);
        $countryRepo = $this->createMock(EntityRepository::class);
        $countryRepo->method('searchIds')->willReturn($countryIdResult);

        $salutationIdResult = $this->buildIdSearchResult(['salutation-id']);
        $salutationRepo = $this->createMock(EntityRepository::class);
        $salutationRepo->method('searchIds')->willReturn($salutationIdResult);

        // Settings: data protection checkbox not required
        $settings = new MollieSettingStruct();
        $settingsService = $this->createMock(SettingsService::class);
        $settingsService->method('getSettings')->willReturn($settings);

        // Capture the RequestDataBag passed to RegisterRoute::register()
        /** @var null|RequestDataBag $capturedBag */
        $capturedBag = null;

        $mockCustomer = $this->createMock(CustomerEntity::class);
        $customerResponse = new CustomerResponse($mockCustomer);

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute->expects($this->once())
            ->method('register')
            ->willReturnCallback(static function (RequestDataBag $data) use (&$capturedBag, $customerResponse): CustomerResponse {
                $capturedBag = $data;

                return $customerResponse;
            })
        ;

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(RegisterRoute::class)->willReturn($registerRoute);

        $fakeCustomerRepo = new FakeCustomerRepository(new CustomerDefinition());

        $service = $this->buildCustomerService(
            $fakeCustomerRepo,
            $this->createMock(EntityRepository::class),
            $countryRepo,
            $salutationRepo,
            $container,
            $settingsService,
        );

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc-1');
        $context->method('getContext')->willReturn($this->createMock(Context::class));
        $context->method('getCustomer')->willReturn(null);

        // Act
        $service->createGuestAccount($shippingAddress, 'payment-method-id', $context, null, $billingAddress);

        // Assert: billingAddress bag must contain the billing street, not the shipping street
        $this->assertNotNull($capturedBag, 'RegisterRoute::register must have been called');

        /** @var RequestDataBag $billingBag */
        $billingBag = $capturedBag->get('billingAddress');
        $this->assertInstanceOf(RequestDataBag::class, $billingBag);

        $this->assertSame(
            'Billing Lane 99',
            $billingBag->get('street'),
            'billingAddress bag must contain billing address data, not a copy of shipping'
        );
        $this->assertNotSame(
            $shippingAddress->getStreet(),
            $billingBag->get('street'),
            'billingAddress street must differ from shippingAddress street'
        );
    }

    private function buildAddress(string $street, string $city, string $zip = '12345'): AddressStruct
    {
        return new AddressStruct('John', 'Doe', 'john@example.com', $street, '', $zip, $city, 'DE', '+49000');
    }

    private function buildIdSearchResult(array $ids): IdSearchResult
    {
        $result = $this->createMock(IdSearchResult::class);
        $result->method('getIds')->willReturn($ids);

        return $result;
    }

    private function buildCustomerService(
        FakeCustomerRepository $customerRepo,
        EntityRepository $customerAddressRepo,
        EntityRepository $countryRepo,
        EntityRepository $salutationRepo,
        ContainerInterface $container,
        SettingsService $settingsService
    ): CustomerService {
        return new CustomerService(
            $countryRepo,
            $customerRepo,
            $customerAddressRepo,
            $this->createMock(Customer::class),
            $this->createMock(EventDispatcherInterface::class),
            new NullLogger(),
            $this->createMock(SalesChannelContextPersister::class),
            $salutationRepo,
            $settingsService,
            '6.5.0',
            $this->createMock(ConfigService::class),
            $container,
            $this->createMock(RequestStack::class),
            new FakeTranslator(),
        );
    }
}
